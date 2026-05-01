<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\AddNewSessionRequest;
use App\Http\Resources\Api\PracticeSessionResource;
use App\Models\CagePracticeMeta;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Services\CreateServiceData;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddNewSession extends Controller
{
    /**
     * @param AddNewSessionRequest $request
     * @return JsonResponse
     */
    public function __invoke(AddNewSessionRequest $request): JsonResponse
    {
        try {
            $players = [];
            DB::beginTransaction();
            $dataRequest = $request->validated();
            $dataRequest['started'] = Carbon::now();
            if ( ! isset($dataRequest['team'])) {
                $dataRequest['user_id'] = Auth::id();
            }
            if (isset($dataRequest['team'])) {
                $dataRequest['team_id'] = $dataRequest['team'];
            }
            $dataRequest['modes'] = $dataRequest['modes']??PracticeModes::HIT_OR_PITCH->value;
            $dataRequest['type'] = $dataRequest['type']??PracticeTypes::TRAINING->value;

            $practice = (new CreateServiceData(new Practice()))->handle($dataRequest);
            $metaCage = null;
            if (isset($dataRequest['cage'])) {
                $metaCage = (new CreateServiceData(new CagePracticeMeta()))->handle([
                    'practice_id' => $practice->id,
                    'height_ft' => $dataRequest['cage']['height']['ft'],
                    'height_inch' => $dataRequest['cage']['height']['inch'],
                    'width_ft' => $dataRequest['cage']['width']['ft'],
                    'width_inch' => $dataRequest['cage']['width']['inch'],
                    'length_ft' => $dataRequest['cage']['length']['ft'],
                    'length_inch' => $dataRequest['cage']['length']['inch'],
                ]);
            }

            foreach ($dataRequest['players'] as $player) {
                $players[] = (new CreateServiceData(new PracticeLineUp()))
                    ->handle([
                        'practice_id' => $practice->id,
                        'user_id' => $player['id'],
                        'sort' => $player['sort'],
                        'is_batting' => $dataRequest['type'] !== PracticeTypes::BULLPEN->value && $dataRequest['type']
                          !== PracticeTypes::TRAINING->value,
                        'is_pitching' => $dataRequest['type'] === PracticeTypes::BULLPEN->value && $dataRequest['type']
                          !== PracticeTypes::TRAINING->value,
                    ]);
            }
            $response = [
                'code' => '006',
                'message' => 'add new session training',
                'status' => 'success',
                'data' => new PracticeSessionResource([
                    'practice' => $practice,
                    'players' => $players,
                    'meta' => $metaCage
                ]),
            ];
            DB::commit();

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '006-E',
                'message' => 'error to create a session training',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
