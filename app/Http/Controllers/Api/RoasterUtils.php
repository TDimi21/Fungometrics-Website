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
        return User::with(['profile', 'player', 'positions'])->whereIn('id', $ids)->get();
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
