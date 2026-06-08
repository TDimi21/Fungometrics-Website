<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddTeamRequest;
use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Models\Concerns\UserTypes;
use App\Services\CreateServiceData;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddTeams extends Controller
{
    // 12 dummy players: alternating bats/throws for realistic L/R mix
    private const DUMMY_PLAYERS = [
        ['first' => 'Alex',    'last' => 'Reyes',  'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Jordan',  'last' => 'Miller', 'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Sam',     'last' => 'Torres', 'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Chris',   'last' => 'Clark',  'bats' => 'L', 'throws' => 'R'],
        ['first' => 'Taylor',  'last' => 'Hill',   'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Drew',    'last' => 'Young',  'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Casey',   'last' => 'Scott',  'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Blake',   'last' => 'Price',  'bats' => 'L', 'throws' => 'R'],
        ['first' => 'Riley',   'last' => 'Brooks', 'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Logan',   'last' => 'Flores', 'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Parker',  'last' => 'Ward',   'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Cameron', 'last' => 'Diaz',   'bats' => 'L', 'throws' => 'R'],
    ];

    /**
     * @param  AddTeamRequest  $request
     * @return JsonResponse
     */
    public function __invoke(AddTeamRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // ── 1. Create the real team ──────────────────────────────────────
            $url  = UploadS3File::getUrl($request->logo, '/teams');
            $data = $request->validated();
            $data['logo']      = $url;
            $data['join_code'] = Team::generateJoinCode();
            $realTeam = (new CreateServiceData(new Team()))->handle($data);

            (new CreateServiceData(new CoachTeam()))->handle([
                'coach_id' => Auth::id(),
                'team_id'  => $realTeam->id,
                'is_main'  => true,
            ]);

            // ── 2. Create the per-team dummy opponent ────────────────────────
            $dummyTeam = (new CreateServiceData(new Team()))->handle([
                'name'          => $realTeam->name . ' Scouts',
                'logo'          => '',
                'state'         => 'SIM',
                'zip'           => '00000',
                'join_code'     => Team::generateJoinCode(),
                'is_dummy'      => true,
                'owner_team_id' => $realTeam->id,
            ]);

            // Link dummy team to the same coach so it shows in their team picker
            (new CreateServiceData(new CoachTeam()))->handle([
                'coach_id' => Auth::id(),
                'team_id'  => $dummyTeam->id,
                'is_main'  => false,
            ]);

            // ── 3. Create 12 dummy players for this dummy team ───────────────
            foreach (self::DUMMY_PLAYERS as $i => $p) {
                $user = (new CreateServiceData(new User()))->handle([
                    'phone'    => 'dummy-' . $dummyTeam->id . '-' . $i,
                    'type'     => UserTypes::PLAYER->value,
                    'status'   => true,
                    'is_dummy' => true,
                ]);

                (new CreateServiceData(new Profile()))->handle([
                    'user_id'    => $user->id,
                    'first_name' => $p['first'],
                    'last_name'  => $p['last'],
                ]);

                (new CreateServiceData(new Player()))->handle([
                    'user_id'    => $user->id,
                    'hit_side'   => $p['bats'],
                    'throw_side' => $p['throws'],
                ]);

                (new CreateServiceData(new PlayerTeam()))->handle([
                    'user_id' => $user->id,
                    'team_id' => $dummyTeam->id,
                ]);
            }

            DB::commit();

            $response = [
                'code'    => '004',
                'message' => 'add team ok',
                'status'  => 'success',
                'data'    => $realTeam,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '004-E',
                'message' => 'error to add team',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

