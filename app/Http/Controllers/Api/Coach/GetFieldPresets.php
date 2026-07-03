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

class GetFieldPresets extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $presets = FieldPreset::where('user_id', Auth::id())
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '090',
                'message' => 'list of field presets',
                'status'  => 'success',
                'data'    => $presets,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetFieldPresets: ' . $e->getMessage());

            return response()->json([
                'code'    => '090-E',
                'message' => 'failed to fetch field presets',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
