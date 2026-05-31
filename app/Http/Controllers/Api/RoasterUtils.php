<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Validator;

class RoasterUtils
{
    /**
     * @param array $ids
     * @return Collection
     */
    public function getDataPlayers(array $ids): Collection
    {
        return User::query()
            ->select(['id', 'email', 'phone'])
            ->with([
                'profile:user_id,first_name,last_name,picture',
                'player:user_id,height_in_ft,height_in_inch,born_date,number_in_shirt,throw_side,hit_side',
                'positions:player_id,position',
                'fitness',
            ])
            ->whereIn('id', $ids)
            ->get();
    }

  /**
   * @param $data
   * @return bool
   */
  public static function isImage($data): bool
  {
      return Validator::make(
          ['image'=> $data,],
          ['image'=>'image']
      )->passes();
  }
}
