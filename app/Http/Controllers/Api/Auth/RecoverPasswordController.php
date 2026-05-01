<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RecoverPasswordController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password)
                ]);

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if(Password::PASSWORD_RESET !== $status) {
            $response = [
                'code' => '',
                'message' => __($status),
                'status' => 'error',
                'data' => [
                    'status' => __($status)
                ],
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        }

        $response = [
            'code' => 'code',
            'message' => __($status),
            'status' => 'success',
            'data' => [
                'status' => __($status)
            ],
        ];

        return response()->json($response, HttpCodes::HTTP_OK);

    }
}
