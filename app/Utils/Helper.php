<?php

declare(strict_types=1);

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class Helper
{
    public static function caseDivide($value1, $value2): float|int
    {

        return 0 === $value2 ? 0 : $value1 / $value2;

    }

    /**
     * @param  int  $range
     * @param  $data
     * @return mixed
     */
    public static function range(int $range, $data)
    {
        $dateCarbon = Carbon::now();
        $subDate1 = $dateCarbon->format('Y-m-d');
        $subSet = $data;

        if (12 === $range) {
            $subDate2 = $dateCarbon->subYear()->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if (6 === $range) {
            $subDate2 = $dateCarbon->subMonths(6)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if (3 === $range) {
            $subDate2 = $dateCarbon->subMonths(3)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if(1 === $range) {
            $subDate2 = $dateCarbon->subMonths(1)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }


        return $subSet->get();
    }

    /**
     * @param  Collection|array  $result
     * @return Collection|\Illuminate\Support\Collection
     */
    public static function getSets(
        Collection|array $result
    ): \Illuminate\Support\Collection|Collection {
        $groups = $result->groupBy('user_id');
        $sets = $groups->map(function ($group) {
            $setMAx = $group->max('set');
            $countBallsxSet = $group->where('set', '=', $setMAx)->count();
            $groupCount = $group->count();
            return [
                'set' => $setMAx,
                'bxs' => $countBallsxSet,
                'balls' => $groupCount
            ];
        });
        return $sets;
    }
}
