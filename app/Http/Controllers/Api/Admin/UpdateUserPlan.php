<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class UpdateUserPlan extends Controller
{
    private const VALID_PLANS = [
        'free',
        'coach_basic',
        'coach_pro',
        'player_basic',
        'player_pro',
    ];

    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $plan = $request->input('subscription_plan');

            if (!in_array($plan, self::VALID_PLANS, true)) {
                return response()->json([
                    'code'    => 'ADMIN-PLAN-400',
                    'message' => 'Invalid subscription plan: ' . $plan,
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = User::findOrFail($id);
            $previous = $user->subscription_plan;
            $user->subscription_plan = $plan;
            $user->save();

            Log::info("Admin updated user #{$id} plan: {$previous} → {$plan}", [
                'admin_id'  => $request->user()?->id,
                'user_id'   => $id,
                'old_plan'  => $previous,
                'new_plan'  => $plan,
            ]);

            return response()->json([
                'code'    => 'ADMIN-PLAN-OK',
                'message' => 'Plan updated successfully',
                'status'  => 'success',
                'data'    => [
                    'id'                => $user->id,
                    'email'             => $user->email,
                    'subscription_plan' => $user->subscription_plan,
                ],
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error('Admin UpdateUserPlan error: ' . $exception->getMessage());
            return response()->json([
                'code'    => 'ADMIN-PLAN-500',
                'message' => 'Error updating plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
