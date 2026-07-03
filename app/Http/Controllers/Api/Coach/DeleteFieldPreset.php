<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\FieldPreset;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DeleteFieldPreset extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        try {
            $preset = FieldPreset::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if ($preset) {
                $preset->delete();
            }

            return response()->json([
                'code'    => '092',
                'message' => 'field preset deleted',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('DeleteFieldPreset: ' . $e->getMessage());

            return response()->json([
                'code'    => '092-E',
                'message' => 'failed to delete field preset',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
