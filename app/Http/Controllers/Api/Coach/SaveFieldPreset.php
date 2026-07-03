<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\FieldPreset;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveFieldPreset extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id'     => ['nullable', 'string', 'max:64'],
                'name'   => ['required', 'string', 'max:120'],
                'config' => ['required', 'array'],
            ]);

            $presetId = $validated['id'] ?? (string) Str::uuid();

            // If updating an existing preset, it must belong to the current user.
            $existing = FieldPreset::find($presetId);
            if ($existing && $existing->user_id !== Auth::id()) {
                return response()->json([
                    'code'    => '091-F',
                    'message' => 'not allowed to edit this field preset',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $preset = FieldPreset::updateOrCreate(
                ['id' => $presetId],
                [
                    'user_id' => Auth::id(),
                    'name'    => $validated['name'],
                    'config'  => $validated['config'],
                ]
            );

            return response()->json([
                'code'    => '091',
                'message' => 'field preset saved',
                'status'  => 'success',
                'data'    => $preset,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SaveFieldPreset: ' . $e->getMessage());

            return response()->json([
                'code'    => '091-E',
                'message' => 'failed to save field preset',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
