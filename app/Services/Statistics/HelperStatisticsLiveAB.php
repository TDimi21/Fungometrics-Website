<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\PitchThrowTypes;
use Illuminate\Support\Collection;

class HelperStatisticsLiveAB
{
    /**
     * @param  mixed  $item
     * @return mixed
     */
    public static function getPitches3less(mixed $item): mixed
    {
        //        $c00 = $item->where('livePractice.count_b_s', '=', '0-0')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c01 = $item->where('livePractice.count_b_s', '=', '0-1')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c02 = $item->where('livePractice.count_b_s', '=', '0-2')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c10 = $item->where('livePractice.count_b_s', '=', '1-0')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c11 = $item->where('livePractice.count_b_s', '=', '1-1')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c12 = $item->where('livePractice.count_b_s', '=', '1-2')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c20 = $item->where('livePractice.count_b_s', '=', '2-0')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //        $c21 = $item->where('livePractice.count_b_s', '=', '2-1')
        //            ->where('livePractice.turn_is_over', '=', true)
        //            ->count();
        //
        //
        //        return $c00 + $c01 + $c02 + $c10 + $c11 + $c12 + $c20 + $c21;
        $totalCount = 0;

        for ($i = 0; $i <= 2; $i++) {
            for ($j = 0; $j <= 1; $j++) {
                $count = $item->where('livePractice.count_b_s', '=', "{$i}-{$j}")
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $totalCount += $count;
            }
        }

        return $totalCount;
    }

    public static function sumBSByPitch(Collection $data): array
    {
        $pitchTypes = [
            'FB' => PitchThrowTypes::FAST_BALL->value,
            'CH' => PitchThrowTypes::CHANGE_UP->value,
            'CB' => PitchThrowTypes::CURVE_BALL->value,
            'SL' => PitchThrowTypes::SLIDER->value,
            'OTHER' => PitchThrowTypes::OTHER->value,
        ];
        $results = [];
        foreach ($pitchTypes as $key => $value) {
            $count = $data
                ->where('pitching.type_throw', '=', $value)
                ->count();
            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function sumBSByContact(Collection $data): array
    {
        $contact = [
            'Weak' => ContactQuality::WEAK->value,
            'Average' => ContactQuality::AVERAGE->value,
            'Hard' => ContactQuality::HARD->value,
        ];
        $results = [];
        foreach ($contact as $key => $value) {
            $count = $data
                ->where('batting.quality_of_contact', '=', $value)
                ->count();
            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function sumBSByTrajectory(Collection $data): array
    {
        $trajectory = [
            'GB' => BattingTrajectory::GROUND_BALL->value,
            'LD' => BattingTrajectory::LINE_DRIVE->value,
            'Fly' => BattingTrajectory::FLY_BALL->value,
            'SW' => BattingTrajectory::SWING_MISS->value,
        ];
        $results = [];
        foreach ($trajectory as $key => $value) {
            $count = $data
                ->where('pitching.trajectory', '=', $value)
                ->count();
            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function countBSxPitchTypes(Collection $data, $bs): array
    {
        $pitchTypes = [
            'FB' => PitchThrowTypes::FAST_BALL->value,
            'CH' => PitchThrowTypes::CHANGE_UP->value,
            'CB' => PitchThrowTypes::CURVE_BALL->value,
            'SL' => PitchThrowTypes::SLIDER->value,
            'OTHER' => PitchThrowTypes::OTHER->value,
        ];
        $results = [];
        foreach ($pitchTypes as $key => $value) {

            $count = $data->where('count_b_s', '=', $bs)
                ->where('pitching.type_throw', '=', $value)
                ->count();

            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function countBSxQualityContact(Collection $data, $bs): array
    {
        $pitch = [
            'Weak' => ContactQuality::WEAK->value,
            'Average' => ContactQuality::AVERAGE->value,
            'Hard' => ContactQuality::HARD->value,
        ];
        $results = [];
        foreach ($pitch as $key => $value) {
            $count = $data->where('count_b_s', '=', $bs)
                ->where('batting.quality_of_contact', '=', $value)
                ->count();
            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function countBSxTrajectory(Collection $data, $bs): array
    {
        $trajectory = [
            'GB' => BattingTrajectory::GROUND_BALL->value,
            'LD' => BattingTrajectory::LINE_DRIVE->value,
            'Fly' => BattingTrajectory::FLY_BALL->value,
            'SW' => BattingTrajectory::SWING_MISS->value,
        ];
        $results = [];
        foreach ($trajectory as $key => $value) {
            $count = $data->where('count_b_s', '=', $bs)
                ->where('pitching.trajectory', '=', $value)
                ->count();
            $results[$key] = $count;
        }
        $results['total'] = array_sum($results);
        return $results;
    }

    public static function countBSx1B(Collection $data, $bs)
    {
        return $data->where('count_b_s', '=', $bs)
            ->where('bases', '=', '1')
            ->count();
    }

    public static function countBSXBH(Collection $data, $bs)
    {
        return $data->where('count_b_s', '=', $bs)
            ->whereIn('bases', ['2','3','5'])
            ->count();
    }

    public static function countBSxBatAvg(Collection $data, $bs)
    {
        return $data->where('count_b_s', '=', $bs)
            ->whereIn('bases', ['1','2','3','5'])
            ->count();
    }

    public static function countBSxSlgPercents(Collection $data, $bs)
    {

        $counts = [];

        $baseValues = [ 2 => 2, 3 => 3, 5 => 4];

        foreach ($baseValues as $base => $value) {
            $counts[$base] = $data->where('count_b_s', $bs)->where('bases', $base)->count();
        }

        return array_sum(array_map(fn ($count, $value) => $count * $value, $counts, $baseValues));
    }

}
