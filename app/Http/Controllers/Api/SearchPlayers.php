<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SearchPlayersResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SearchPlayers extends Controller
{
    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $name  = trim((string) ($request->name  ?? ''));
            $phone = trim((string) ($request->phone ?? ''));

            $uniqueResults = $this->getDataFromNameAndPhone($phone, $name);
            $responseData  = collect(SearchPlayersResource::collection($uniqueResults));
            $paginatedPage = $this->paginator($responseData, 15);

            $response = [
                'code'    => '042',
                'message' => 'list of player search',
                'status'  => 'success',
                'data'    => collect()->merge($paginatedPage['data'])->values(),
                'links'   => $paginatedPage['links'],
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code'    => '042-E',
                'message' => 'Not Results Found',
                'status'  => 'error',
                'data'    => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }

    /**
     * @param  string  $phone
     * @param  string  $name
     * @return mixed
     */
    public function getDataFromNameAndPhone(string $phone, string $name): mixed
    {
        $hasFilter = $phone !== '' || $name !== '';

        $query = DB::table('users as u')
            ->select(
                'u.id as id',
                'u.phone',
                'u.email',
                'u.type',
                't.name as team_name',
                'p.first_name',
                'p.last_name',
                'p.picture',
                'p2.born_date',
                't.id as team_id',
                't.join_code as team_join_code'
            )
            ->join('profiles as p', 'u.id', '=', 'p.user_id')
            ->join('players as p2', 'u.id', '=', 'p2.user_id')
            // LEFT JOIN so players with no team still appear (INNER JOIN hid them)
            ->leftJoin('player_teams as pt', 'u.id', '=', 'pt.user_id')
            ->leftJoin('teams as t', 'pt.team_id', '=', 't.id')
            ->where('u.type', '=', 'player');

        if ($hasFilter) {
            // When a search term is provided, filter by phone AND/OR name.
            // Also match players with NULL phone so they aren't silently excluded.
            if ($phone !== '') {
                $query->where(function ($q) use ($phone) {
                    $q->where('u.phone', 'like', '%' . $phone . '%')
                      ->orWhereNull('u.phone');
                });
            }
            if ($name !== '') {
                $query->where('p.first_name', 'like', '%' . $name . '%');
            }
        }
        // No filter → return ALL players (no WHERE clause on phone/name)

        $result = $query->get();
        $data   = $result->groupBy('id');

        return $data->map(function ($group) {
            // Filter out NULL team rows that appear because of LEFT JOIN
            $teamInfo = $group
                ->filter(fn ($row) => $row->team_id !== null)
                ->map(fn ($row) => [
                    'id'        => $row->team_id,
                    'name'      => $row->team_name,
                    'join_code' => $row->team_join_code ?? '',
                ]);

            return [
                'id'         => $group->first()->id,
                'phone'      => $group->first()->phone,
                'email'      => $group->first()->email,
                'type'       => $group->first()->type,
                'first_name' => $group->first()->first_name,
                'last_name'  => $group->first()->last_name,
                'picture'    => $group->first()->picture,
                'born_date'  => $group->first()->born_date,
                'teams'      => $teamInfo->values()->toArray(),
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $responseData
     * @param  int  $perPage
     * @return array
     */
    public function paginator(\Illuminate\Support\Collection $responseData, int $perPage = 15): array
    {
        $page    = LengthAwarePaginator::resolveCurrentPage();
        $segment = $responseData->slice(($page - 1) * $perPage, $perPage);
        $paginate = new LengthAwarePaginator($segment, $responseData->count(), $perPage);

        return $paginate->toArray();
    }
}
