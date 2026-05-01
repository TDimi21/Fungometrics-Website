<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\LiveABPracticeResult;
use App\Services\Statistics\HelperStatisticsLiveAB;
use App\Services\Statistics\LiveABStatisticsService;
use App\Utils\Helper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetLiveABPracticeResults extends Controller
{
    protected array $bsCount;
    private ServiceCountBSLiveAB $serviceCountBSLiveAB;

    public function __construct()
    {
        $this->bsCount = config('constants.countBS');
        $this->serviceCountBSLiveAB = new ServiceCountBSLiveAB($this, $this->bsCount);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $stat = new LiveABStatisticsService();
            $calculateData = [];

            $data = collect()->merge(LiveABPracticeResult::where('practice_id', $request->practice)
                ->orderBy('sort', 'desc')
                ->with('pitching.profile', 'batting.profile')
                ->get());

            if (0 === $data->count()) {
                throw  new NotFound();
            }


            $count = $data->count();
            $pitchers = collect()->merge($data->sortBy('sort')->groupBy('pitching.profile.user_id')->all());
            $batters = collect()->merge($data->sortBy('sort')->groupBy('batting.profile.user_id')->all());

            $matrixBS = $this->resultBSTeams($data, $this->bsCount);
            $matrixBSxBatter = $this->resultBSPlayers($data, 'batting.batter_id');
            $matrixBSxPitcher = $this->resultBSPlayers($data, 'pitching.pitcher_id');

            $players['batters'] = BattingPracticeResult::with('livePractice')
                ->whereIn('practice_id', [$request->practice])
                ->where('is_in_match', true)->get();
            $players['pitchers'] = BullpenPracticeResult::with('livePractice')
                ->whereIn('practice_id', [$request->practice])
                ->where('is_in_match', true)->get();

            $liveOptions = [
                48 => ['label' => 'hitter-basic', 'method' => 'hitterResults', 'params' => 'false,1'],
                49 => ['label' => 'hitter-advance', 'method' => 'hitterResults', 'params' => false],
                50 => ['label' => 'pitcher-basic', 'method' => 'pitcherResults', 'params' => null],
                51 => ['label' => 'pitcher-advance', 'method' => 'pitcherResults', 'params' => false],
                52 => ['label' => 'hitter-pitch-breakdown', 'method' => 'hitterPitchBreakdown', 'params' => null],
                53 => ['label' => 'hitter-contact', 'method' => 'hitterContact', 'params' => null],
                54 => ['label' => 'hitter-trajectory', 'method' => 'hitterTrajectory', 'params' => null],
                55 => ['label' => 'hitter-velocity', 'method' => 'hitterVelocity', 'params' => null],
                56 => ['label' => 'pitcher-pitch-breakdown', 'method' => 'pitcherPitchBreakdown', 'params' => null],
                57 => ['label' => 'pitcher-contact', 'method' => 'pitcherContact', 'params' => null],
                58 => ['label' => 'pitcher-velocity', 'method' => 'pitcherVelocity', 'params' => null]

            ];

            foreach ($liveOptions as $key) {
                if (null !== $key['params']) {
                    $calculateData[$key['label']] = $stat->{$key['method']}($players, $key['params']);
                } else {
                    $calculateData[$key['label']] = $stat->{$key['method']}($players);
                }
            }

            $response = [
                'code' => '041',
                'message' => 'data from practice liveab',
                'status' => 'success',
                'data' => [
                    'count' => $count,
                    'ball_x_ball' => $data,
                    'by_pitchers' => $pitchers,
                    'by_batters' => $batters,
                    'matrixBS' => $matrixBS,
                    'matrixBSPB' => $matrixBSxBatter,
                    'matrixBSPH' => $matrixBSxPitcher,
                    'calculates' => $calculateData,
                ],
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '041-E',
                'message' => 'Not Data Found for liveab training',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }

    /**
     * @param  Collection  $data
     * @return array
     */
    public function resultBSTeams(Collection $data): array
    {
        $plateAppearance = $data->where('count_b_s', '=', '0-0')->count();
        $notCount = $data->whereIn('bases', [4,6])->count();
        $atBat = $plateAppearance-$notCount;

        $matrixBS['pitch'] = $this->serviceCountBSLiveAB->getBSTeamPitch($data);
        $matrixBS['contact'] = $this->serviceCountBSLiveAB->getBSTeamContactQuality($data);
        $matrixBS['trajectory'] = $this->serviceCountBSLiveAB->getBSTeamTrajectory($data);

        $matrixBS['1b'] = $this->serviceCountBSLiveAB->getBSTeam1B($data);
        $matrixBS['XBH'] = $this->serviceCountBSLiveAB->getBSTeamXHB($data, $atBat);
        $matrixBS['SLG%'] = $this->serviceCountBSLiveAB->getBSTeamSLGPercent($data, $atBat);
        $matrixBS['bat_avg'] = $this->serviceCountBSLiveAB->getBSTeamBatAvg($data, $atBat);
        $matrixBS['ab'] = $this->calculateAtBatData($data, $atBat);
        $matrixBS['other']=['pa'=>$plateAppearance,'count(BB,HBP)'=>$notCount,'atBat'=>$atBat];
        return $matrixBS;
    }
    public function resultBSPlayers($data, $group)
    {

        $results = null;
        $calculates = $data->groupBy($group);
        foreach ($calculates as $key => $players) {
            $plateAppearance = $players->where('count_b_s', '=', '0-0')->count();
            $notCount = $players->whereIn('bases', [4,6])->count();
            $atBat = $plateAppearance-$notCount;

            foreach ($this->bsCount as $bs) {
                $results['pitch']['b-s'][$key][$bs] = HelperStatisticsLiveAB::countBSxPitchTypes($players, $bs);
                $results['contact']['b-s'][$key][$bs] = HelperStatisticsLiveAB::countBSxQualityContact($players, $bs);
                $results['trajectory']['b-s'][$key][$bs] = HelperStatisticsLiveAB::countBSxTrajectory($players, $bs);
                $results['1b'][$key][$bs] = HelperStatisticsLiveAB::countBSx1B($players, $bs);
                $results['XHB'][$key][$bs] = HelperStatisticsLiveAB::countBSXBH(
                    $players,
                    $bs);
                $results['SLG'][$key][$bs] = round(
                    Helper::caseDivide(HelperStatisticsLiveAB::countBSxSlgPercents($players, $bs), $atBat),
                    4);
                $results['bat_avg'][$key][$bs] = round(Helper::caseDivide(HelperStatisticsLiveAB::countBSxBatAvg($players, $bs), $atBat), 4);
                $results['ab'][$key] = $this->calculateAtBatData($players, $atBat);
            }
            $results['pitch']['b-s'][$key]['total'] = HelperStatisticsLiveAB::sumBSByPitch($players);
            $results['contact']['b-s'][$key]['total'] = HelperStatisticsLiveAB::sumBSByContact($players);
            $results['1b'][$key]['total'] = array_sum($results['1b'][$key]);
            $results['XHB'][$key]['total'] = array_sum($results['XHB'][$key]);
            $results['SLG'][$key]['total'] = round(array_sum($results['SLG'][$key]), 4);
            $results['bat_avg'][$key]['total'] = round(array_sum($results['bat_avg'][$key]),4);
            $results['other'][$key]=['pa'=>$plateAppearance,'count(BB,HBP)'=>$notCount,'atBat'=>$atBat];
            ///// totals
            $results['pitch']['b-s'][$key]['total'] = HelperStatisticsLiveAB::sumBSByPitch($players);
            $results['contact']['b-s'][$key]['total'] = HelperStatisticsLiveAB::sumBSByContact($players);
            $results['trajectory']['b-s'][$key]['total'] = HelperStatisticsLiveAB::sumBSByTrajectory($players);
        }

        return $results;
    }

    private function calculateAtBatData(Collection $data, $atBat)
    {

        $pitchCount = [];

        $turns = [1, 2, 3, 4, 5];
        foreach ($turns as $turn) {
            $pitchCount[$turn]['count'] = $data->where('turn_is_over', true)->where('turn', $turn)->count();
        }
        $pitchCount['6+']['count'] = $data->where('turn_is_over', true)->where('turn', '>=', 6)->count();

        foreach ($turns as $turn) {
            $pitchCount[$turn]['average'] = round(
                Helper::caseDivide($pitchCount[$turn]['count'], $atBat) * 100,
                2
            );
        }
        $pitchCount['6+']['average'] = round(Helper::caseDivide($pitchCount['6+']['count'], $atBat) * 100, 2);
        return $pitchCount;
    }

}
