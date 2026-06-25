<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\PlayerUtils;
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

            // Photo upload is best-effort. UploadS3File::getUrl() throws if the S3 write
            // fails — but that must NOT roll back the rest of the save (height, name,
            // positions, etc.). Catch it, keep the existing picture, and report the
            // failure separately so the client can retry just the photo. This is why
            // height previously "didn't recall": a failed photo upload reverted everything.
            $photoError = null;
            if ($request->hasFile('picture')) {
                try {
                    $playerProfile['picture'] = UploadS3File::getUrl(
                        $request->file('picture'),
                        '/players',
                    );
                } catch (Exception $photoException) {
                    $photoError = $photoException->getMessage();
                    Log::error('Player photo upload failed for '.$request->id.': '.$photoError);
                }
            }
            $player->profile->update($playerProfile);
            $playerInput = $request->get('player') ?? [];
            $playerData = [
                'height_in_ft' => $playerInput['ft'] ?? 0,
                'height_in_inch' => $playerInput['inch'] ?? 0,
                // Use null-coalescing on every key. Previously ['born'] and ['shirt']
                // were accessed directly, so a missing key threw "Undefined array key"
                // and the whole save failed with "player not updated" (picture included).
                'born_date' => $playerInput['born'] ?? null,
                'number_in_shirt' => $playerInput['shirt'] ?? null,
                'hit_side' => $playerInput['sides']['hit'] ?? "",
                'throw_side' => $playerInput['sides']['pitch'] ?? "",
            ];

            $player->player()->updateOrCreate(['user_id' => $player->id], $playerData);

            $player->positions->where('player_id', $request->id)->each->delete();
            PlayerUtils::savePositionsPlayer($request, $request->id);

            $response = [
                'code' => '033',
                'message' => $photoError ? 'player updated (photo not saved)' : 'player updated',
                'status' => 'success',
                'photo_uploaded' => $photoError === null,
                'data' => User::with('profile', 'player', 'positions')->find($request->id),
            ];
            if ($photoError) {
                $response['photo_error'] = 'Photo could not be stored; other changes were saved.';
            }
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
