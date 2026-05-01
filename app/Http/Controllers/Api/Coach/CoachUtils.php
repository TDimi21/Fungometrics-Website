<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotCreated;
use App\Exceptions\NotFound;
use App\Exceptions\UpdateException;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\User;
use App\Services\CreateServiceData;
use App\Services\ListServiceData;
use Illuminate\Database\Eloquent\Model;

class CoachUtils
{
    /**
     * @param  array  $data
     * @param  string  $type
     * @return array
     *
     * @throws NotCreated
     */
    public static function saveNewUser(array $data, string $type = 'player'): array
    {
        $response = collect();
        $response_user = (new CreateServiceData(new User()))->handle([
            'phone' => $data['phone'],
            'type' => $type,
            'status'=>true
        ]);
        $response_profile = (new CreateServiceData(new Profile()))->handle([
            'user_id' => $response_user->id,
            'first_name' => $data['name']['first'],
            'last_name' => $data['name']['last'],
        ]);

        if ($type === UserTypes::COACH->value) {
            (new CreateServiceData(new CoachTeam()))->handle([
                'coach_id' => $response_user->id,
                'team_id' => $data['team'],
                'actual' => true,
            ]);
        }
        if ($type === UserTypes::PLAYER->value) {
            (new CreateServiceData(new PlayerTeam()))->handle([
                'user_id' => $response_user->id,
                'team_id' => $data['team'],
                'actual' => true,
            ]);
        }
        $response->put('user', $response_user);
        $response->put('profile', $response_profile);

        return $response->all();
    }

    /**
     * @param  Model  $player
     * @param  string  $team_id
     * @return Model
     *
     * @throws NotCreated
     * @throws NotFound
     * @throws UpdateException
     */
    public static function addPlayerToRoaster(Model $player, string $team_id): array
    {
        $team_player_actual = (new ListServiceData(new PlayerTeam()))->byParams([
            'user_id' => $player->id,
            'team_id' => $team_id,
        ]);

        if (1 === $team_player_actual->count()) {

            return [
                'data'=>$team_player_actual->first()->toArray(),
                'exist'=>true,
            ];
        }

        return [
            'data'=>(new CreateServiceData(new PlayerTeam()))
                ->handle([
                    'team_id' => $team_id,
                    'user_id' => $player->id,
                    'actual' => true,
                ])->toArray(),
            'exist'=>false
        ];
    }
}
