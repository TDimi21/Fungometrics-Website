<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Illuminate\Support\Facades\Password;

class SendEmailRecoverController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if(Password::RESET_LINK_SENT !== $status) {
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
