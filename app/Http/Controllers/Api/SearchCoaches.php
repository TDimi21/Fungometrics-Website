<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\User;
use App\Services\Access\AdministrativeAccess;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SearchCoaches extends Controller
{
    public function __construct(private AdministrativeAccess $administrativeAccess)
    {
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $search = trim((string) ($request->search ?? ''));
            $isAdmin = $this->administrativeAccess->canManageSubscriptions($request->user());

            if ( ! $isAdmin && mb_strlen($search) < 3) {
                return response()->json([
                    'code' => '043-V',
                    'message' => 'Enter at least 3 characters to search coaches.',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = User::with('profile')
                ->where('type', '=', UserTypes::COACH->value)
                ->where('is_dummy', '=', false);

            if ( ! $isAdmin) {
                $teamIds = CoachTeam::query()
                    ->where('coach_id', $request->user()->id)
                    ->pluck('team_id');
                $query->whereHas('teamsCoach', fn ($membership) => $membership->whereIn('team_id', $teamIds));
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('phone', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhereHas('profile', function ($p) use ($search) {
                          $p->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%");
                      });
                });
            }

            $data = $query->paginate()->through(static function (User $coach) use ($isAdmin): array {
                $profile = $coach->profile;
                return array_filter([
                    'id' => $coach->id,
                    'type' => (string) $coach->type,
                    'email' => $isAdmin ? $coach->email : null,
                    'phone' => $isAdmin ? $coach->phone : null,
                    'profile' => [
                        'first_name' => $profile?->first_name,
                        'last_name' => $profile?->last_name,
                        'picture' => $profile?->picture,
                    ],
                ], static fn ($value): bool => null !== $value);
            });

            $response = [
                'code'    => '043',
                'message' => 'list of coaches search',
                'status'  => 'success',
                'data'    => $data,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code'    => '043-E',
                'message' => 'Not Results Found',
                'status'  => 'error',
                'data'    => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
