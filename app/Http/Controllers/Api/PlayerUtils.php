<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\NotCreated;
use App\Http\Requests\Api\Auth\RegisterPlayerRequest;
use App\Http\Requests\Api\Coach\EditPlayerRequest;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Services\CreateServiceData;
use Illuminate\Database\Eloquent\Model;

class PlayerUtils
{
    /**
     * @param  RegisterPlayerRequest  $request
     * @param  Model  $response_user
     * @return Model
     *
     * @throws NotCreated
     */
    public static function savePlayer(RegisterPlayerRequest $request, Model $response_user): Model
    {
        $player = new CreateServiceData(new Player());
        $request_player_data = [
            'height_in_ft' => $request->get('player')['ft'] ?? 0,
            'height_in_inch' => $request->get('player')['inch'] ?? 0,
            'weight' => $request->get('player')['weight'] ?? 0,
            'born_date' => $request->get('player')['born'],
            'user_id' => $response_user->id,
        ];

        if (isset($request->get('player')['shirt'])) {
            $request_player_data['number_in_shirt'] = $request->get('player')['shirt'];
        }

        return $player->handle($request_player_data);
    }

    /**
     * @param  Model  $response_user
     * @return void
     *
     * @throws NotCreated
     */
    public static function createPlayerFitness(Model $response_user): void
    {
        $fitness = new CreateServiceData(new PlayerFitness());
        $fitness_data = [
            'user_id' => $response_user->id,
        ];
        $fitness->handle($fitness_data);
    }

    /**
     * @param  RegisterPlayerRequest |EditPlayerRequest  $request
     * @param  string  $responseUser
     * @return array
     *
     * @throws NotCreated
     */
    public static function savePositionsPlayer(RegisterPlayerRequest|EditPlayerRequest $request, string $responseUser): array
    {
        $positionResponse = [];

        foreach ($request->get('positions') as $position) {
            $positionObj = new CreateServiceData(new PlayerPosition());
            $dataPosition = [
                'position' => $position['position'],
                'player_id' => $responseUser,
            ];
            $positionResponse[] = $positionObj->handle($dataPosition);
        }

        return $positionResponse;
    }
}
