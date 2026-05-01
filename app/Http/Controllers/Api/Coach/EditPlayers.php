<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\PlayerUtils;
use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\EditPlayerRequest;
use App\Models\User;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class EditPlayers extends Controller
{
    /**
     * @param EditPlayerRequest $request
     * @return JsonResponse
     */
    public function __invoke(EditPlayerRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $player = User::findOrFail($request->id);
            $dataEdit = [
                'email' => $request->email,
                'phone' => $request->phone,
            ];

            $player->update($dataEdit);
            $playerProfile = [
                'first_name' => $request->get('profile')['name']['first'],
                'last_name' => $request->get('profile')['name']['last'],
            ];

            if (RoasterUtils::isImage($request->picture)) {
                $playerProfile['picture'] = UploadS3File::getUrl($request->picture, '/players');
            }
            $player->profile->update($playerProfile);
            $playerData = [
                'height_in_ft' => $request->get('player')['ft'] ?? 0,
                'height_in_inch' => $request->get('player')['inch'] ?? 0,
                'born_date' => $request->get('player')['born'],
                'number_in_shirt' => $request->get('player')['shirt'],
                'hit_side'=>$request->get('player')['sides']['hit']??"",
                'throw_side'=>$request->get('player')['sides']['pitch']??"",
            ];

            $player->player()->updateOrCreate(['user_id' => $player->id], $playerData);

            $player->positions->where('player_id', $request->id)->each->delete();
            PlayerUtils::savePositionsPlayer($request, $request->id);

            $response = [
                'code' => '033',
                'message' => 'player updated',
                'status' => 'success',
                'data' => User::with('profile', 'player', 'positions')->find($request->id),
            ];
            DB::commit();
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '033-E',
                'message' => 'player not updated',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
