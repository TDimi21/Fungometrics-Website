<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\FilterRequest;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Utils\Filters\BattingFilters;
use App\Utils\Filters\BullpenFilters;
use App\Utils\Filters\CageFilters;
use App\Utils\Filters\ExitVelocityFilters;
use App\Utils\Filters\LiveABFilters;
use App\Utils\Filters\LongTossFilters;
use App\Utils\Filters\WeightBallFilters;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class FilterTrainings extends Controller
{
    /**
     * @param FilterRequest $request
     * @return JsonResponse
     */
    public function __invoke(FilterRequest $request): JsonResponse
    {
        try {
            $data=[];
            $params = $request->validated();
            $params['team'] = $request->team;
            $params['options'] = $this->decodeOptions($params['options']);

            $data['batting'] = $this->battingFilters($params);
            $data['bullpen'] = $this->bullpenFilters($params);
            $data['cage'] = $this->cageFilters($params);
            $data['weight_ball'] = $this->weightBallFilters($params);
            $data['exit_velocity'] = $this->exitVelocityFilters($params);
            $data['long_toss'] = $this->longTossFilters($params);
            $data['live'] = $this->liveABFilters($params);


            $response = [
                'code' => '046',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '046-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }

    /**
     * @param array $params
     * @return array
     */
    public function battingFilters(array $params): array
    {
        if(array_key_exists(PracticeTypes::BATTING->value, $params['options'])) {
            return (new BattingFilters())->handle($params);
        }
        return [];
    }

  /**
   * @param array $params
   * @return array
   */
  public function bullpenFilters(array $params): array
  {
      if(array_key_exists(PracticeTypes::BULLPEN->value, $params['options'])) {
          return (new BullpenFilters())->handle($params);
      }
      return [];
  }

  /**
   * @param array $params
   * @return array
   */
  public function cageFilters(array $params): array
  {
      if(array_key_exists(PracticeTypes::CAGE->value, $params['options'])) {
          return (new CageFilters())->handle($params);
      }
      return [];
  }

  public function weightBallFilters(array $params): array
  {
      if(array_key_exists(PracticeModes::WEIGHT_BALL->value, $params['options'])) {
          return (new WeightBallFilters())->handle($params);
      }
      return [];
  }

  public function exitVelocityFilters(array $params): array
  {
      if(array_key_exists(PracticeModes::EXIT_VELOCITY->value, $params['options'])) {
          return (new ExitVelocityFilters())->handle($params);
      }
      return [];
  }

  public function longTossFilters(array $params): array
  {
      if(array_key_exists(PracticeModes::LONG_TOSS->value, $params['options'])) {
          return (new LongTossFilters())->handle($params);
      }
      return [];
  }

    public function liveABFilters(array $params): array
    {
        if(array_key_exists(PracticeTypes::LIVE_AB->value, $params['options'])) {
            return (new LiveABFilters())->handle($params);
        }
        return [];

    }

  public function decodeOptions($options)
  {
      if(is_array($options)) {
          return $options;
      }

      $options_array = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
      return $options_array;
  }
}
