<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountDeletionController extends Controller
{
    public function status(Request $request, AccountDeletionService $service): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $service->status($request->user())]);
    }

    public function authorizeDeletion(Request $request, AccountDeletionService $service): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'min:8', 'max:128']]);
        return response()->json(['status' => 'success', 'data' => $service->authorize(
            $request->user(),
            $validated['password'],
            $request
        )]);
    }

    public function destroy(Request $request, AccountDeletionService $service): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_token' => ['required', 'string', 'size:64'],
            'confirmation' => ['required', 'string', 'in:DELETE'],
        ]);
        return response()->json(['status' => 'success', 'data' => $service->delete(
            $request->user(),
            $validated['confirmation_token'],
            $validated['confirmation'],
            $request
        )]);
    }
}
