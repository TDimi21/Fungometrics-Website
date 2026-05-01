<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\NotFound;
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

            $name = $request->name ?? "";
            $phone = $request->phone ?? "";
            $uniqueResults = $this->getDataFromNameAndPhone($phone, $name);
            $combineData = $uniqueResults;
            $responseData = collect(SearchPlayersResource::collection($uniqueResults));
            $response = $this->paginator($responseData, 15);
            $response = [
                'code' => '042',
                'message' => 'list of player search',
                'status' => 'success',
                'data' =>  collect()->merge($response['data'])->values(),
                'links'=> $response['links'],

            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '042-E',
                'message' => 'Not  Results Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }

    /**
     * @param  mixed  $phone
     * @param  mixed  $name
     * @return mixed
     * @throws NotFound
     */
    public function getDataFromNameAndPhone(mixed $phone, mixed $name)
    {
        $result = DB::table('users as u')
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
                't.id as team_id'
            )
            ->join('profiles as p', 'u.id', '=', 'p.user_id')
            ->join('players as p2', 'u.id', '=', 'p2.user_id')
            ->join('player_teams as pt', 'u.id', '=', 'pt.user_id')
            ->join('teams as t', 'pt.team_id', '=', 't.id')
            ->where('u.type', '=', 'player')
            ->where('u.phone', 'like', '%'.$phone.'%')
            ->where('p.first_name', 'like', '%'.$name.'%')
            ->get();


        $data = $result->groupBy('id');
        if (0 === $data->count()) {
            throw new NotFound();
        }

        return $data->map(function ($group) {
            $teamInfo = $group->map(function ($subGroup) {
                return
                    ['name' => $subGroup->team_name];

            });

            return [
                'id' => $group->first()->id,
                'phone' => $group->first()->phone,
                'email' => $group->first()->email,
                'type' => $group->first()->type,
                'first_name' => $group->first()->first_name,
                'last_name' => $group->first()->last_name,
                'picture' => $group->first()->picture,
                'born_date' => $group->first()->born_date,
                'teams' => $teamInfo->toArray(),
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $responseData
     * @param $perPage
     * @return array
     */
    public function paginator(\Illuminate\Support\Collection $responseData, int $perPage=15): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $segment = $responseData->slice(($page - 1) * $perPage, $perPage);
        $paginate = new LengthAwarePaginator($segment, $responseData->count(), $perPage);
        $result = $paginate->toArray();
        return $result;
    }
}
