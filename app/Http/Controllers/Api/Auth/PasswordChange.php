<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class PasswordChange extends Controller
{
    /**
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $changeData = $request->validated();
            $password = Hash::check($changeData['old'], $user->getAuthPassword());
            $this->validatedPassword($password, $changeData);
            $user->password = Hash::make($request->input('password'));
            $user->save();

            $response = [
                'code' => '055',
                'message' => 'password change',
                'status' => 'success',
                'data' => [],
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $message = 'Error to process password';
            if(99 === $exception->getCode()) {
                $message = $exception->getMessage();
            }
            $response = [
                'code' => '055-E',
                'message' => $message,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @param bool $password
     * @param mixed $changeData
     * @return void
     * @throws Exception
     */
    public function validatedPassword(bool $password, mixed $changeData): void
    {
        if ( ! $password) {
            throw new Exception("the current password does not correspond to the one registered ", 99);
        }
        if ($password && $changeData['old'] === $changeData['password']) {
            throw new Exception("the old password not same a new password", 99);
        }
    }
}
