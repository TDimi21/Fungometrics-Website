<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddPlayers extends Controller
{
    /**
     * @param  AddUserRequest  $request
     * @return JsonResponse
     */
    public function __invoke(AddUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        app(\App\Services\Communications\InvitationService::class)->authorize($request->user(), $data['team']);
        try {
            DB::beginTransaction();

            // ── Player limit enforcement ──
            $teamId = $data['team'] ?? null;
            if ($teamId) {
                // Serialize capacity checks for this team so concurrent invites
                // cannot both observe the same remaining seat.
                Team::query()->whereKey($teamId)->lockForUpdate()->firstOrFail();
            }
            $playerLimit = $teamId ? CoachUtils::playerLimit($teamId) : null;
            if ($teamId && null !== $playerLimit) {
                $currentCount = PlayerTeam::where('team_id', $teamId)
                    ->where('actual', true)
                    ->count();
                if ($currentCount >= $playerLimit) {
                    DB::rollBack();
                    return response()->json([
                        'code'    => '016-LIMIT',
                        'message' => "You have reached the {$playerLimit}-player limit on your current plan.",
                        'status'  => 'error',
                        'data'    => [],
                    ], HttpCodes::HTTP_FORBIDDEN);
                }
            }
            $email = strtolower(trim($data['email'] ?? ''));
            $phone = preg_replace('/\D+/', '', $data['phone'] ?? '');
            $matches = User::query()->when($phone === '', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))->cursor()->filter(fn ($user) =>
                ($email !== '' && strtolower((string) $user->email) === $email) ||
                ($phone !== '' && preg_replace('/\D+/', '', (string) $user->phone) === $phone))->take(2)->collect();
            if ($matches->count() > 1 || ($matches->first() && (string) $matches->first()->type !== 'player')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['contact' => 'These contact details cannot be used for this player.']);
            }
            $player = $matches->first();
            $message = "";
            $changedEventData = null;
            if ( ! isset($player)) {
                validator($data, app(\App\Services\Consent\PlayerConsentService::class)->acceptanceRules())->validate();
                $savePlayer = CoachUtils::saveNewUser($data);
                event(new UserCreated($savePlayer['user']));
                if (!empty($data['player']['position'])) $savePlayer['user']->positions()->create(['position' => $data['player']['position']]);
                $message = 'the player is added to team';
            } else {
                $data_team = CoachUtils::addPlayerToRoaster($player, $data['team']);
                if($data_team['exist']) {
                    $message = 'this player already belongs to the team';
                } else {
                    $message = 'the player is added to team';
                    $changedEventData = ['user' => $player, 'team' => $data_team];
                }
            }

            $userId = $player?->id ?? $savePlayer['user']->id;
            $playerInput = $data['player'] ?? [];
            if (isset($playerInput['grad_year']) && $playerInput['grad_year']) {
                Player::query()->updateOrCreate(
                    ['user_id' => (string) $userId],
                    ['grad_year' => (int) $playerInput['grad_year']]
                );
            }
            $response = [
                'code' => '016',
                'message' => $message,
                'status' => 'success',
                'data' =>  User::with('profile', 'player', 'fitness', 'positions')->find($userId),
            ];
            $claimUser = User::findOrFail($userId);
            if (!filled($claimUser->password)) {
                $claimTeam = Team::findOrFail($data['team']);
                if (empty($claimTeam->join_code)) {
                    $claimTeam->join_code = Team::generateJoinCode();
                    $claimTeam->save();
                }
                $response['team_code'] = $claimTeam->join_code;
            }
            if (!isset($player)) app(\App\Services\Consent\PlayerConsentService::class)->record($claimUser, $request->user(), $data['team'], 'coach_create', $request);
            DB::commit();
            \Illuminate\Support\Facades\Cache::forget("roster_team_{$data['team']}");
            if (null !== $changedEventData) {
                event(new UserChanged($changedEventData));
            }

            $response['invitation'] = ['status' => 'not_requested'];
            if ($request->boolean('send_invitation')) {
                // Profile transaction has committed. Invitation failure must never prompt duplicate creation.
                try {
                    if (!$claimUser->email) throw new \RuntimeException('missing_email');
                    $invite = app(\App\Services\Communications\InvitationService::class)->send($request->user(), $data['team'], ['role' => 'player', 'user_id' => $userId, 'email' => $claimUser->email]);
                    $response['invitation'] = ['status' => 'queued', 'id' => $invite->id, 'message' => 'Invitation queued; delivery remains subject to communications settings.'];
                } catch (\Throwable $e) {
                    $message = $e instanceof \Illuminate\Validation\ValidationException ? implode(' ', \Illuminate\Support\Arr::flatten($e->errors())) : 'Invitation was not queued. Check email, eligibility and communications settings, then send it from Team Communications.';
                    $response['invitation'] = ['status' => 'not_queued', 'message' => $message];
                }
            }
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '016-E',
                'message' => 'error to add player ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
