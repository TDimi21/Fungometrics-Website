<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetUserCompleteController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var AccountClaim $claim */
        $claim = $request->attributes->get('account_claim');
        $user = $claim->user;

        return response()->json([
            'code' => 'claim_valid',
            'message' => '',
            'status' => 'success',
            'data' => [
                'user' => [
                    'phone' => $user->phone,
                    'type' => $claim->intended_type,
                    'profile' => $user->profile ? [
                        'first_name' => $user->profile->first_name,
                        'last_name' => $user->profile->last_name,
                    ] : null,
                ],
            ],
        ], HttpCodes::HTTP_OK);
    }
}
