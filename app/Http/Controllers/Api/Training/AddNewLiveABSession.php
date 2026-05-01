<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\AddNewLiveABSessionRequest;
use App\Http\Resources\Api\PracticeLiveABSessionResource;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\TeamsLiveAB;
use App\Services\CreateServiceData;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddNewLiveABSession extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(AddNewLiveABSessionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $dataRequest = $request->validated();
            $dataRequest['started'] = Carbon::now();
            $dataRequest['modes'] = $dataRequest['modes']??PracticeModes::HIT_OR_PITCH->value;
            $dataRequest['type'] = $dataRequest['type']??PracticeTypes::LIVE_AB->value;
            $practice = (new CreateServiceData(new Practice()))->handle($dataRequest);
            $players = null;

            $teams = null;
            foreach ($dataRequest['teams'] as $team) {
                $teams[] = (new CreateServiceData(new TeamsLiveAB()))->handle([
                    'team_id'=>$team,
                    'practice_id'=>$practice->id
                ]);
            }

            foreach ($dataRequest['players'] as $key=>$team) {
                foreach ($team as $player) {
                    $players[$key][] = (new CreateServiceData(new PracticeLineUp()))
                        ->handle([
                            'practice_id' => $practice->id,
                            'user_id' => $player['id'],
                            'sort' => $player['sort'],
                            'is_batting'=>'a' === $key,
                            'is_pitching'=>'b' === $key,
                        ]);
                }
            }

            $response = [
                'code' => '022',
                'message' => 'practice live ab create',
                'status' => 'success',
                'data' => new PracticeLiveABSessionResource([
                    'practice'=>$practice,
                    'teams'=>$teams,
                    'players'=>$players
                ]),
            ];
            DB::commit();
            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '022-E',
                'message' => 'error to create a session training',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
