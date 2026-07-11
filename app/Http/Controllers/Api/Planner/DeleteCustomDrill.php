<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\PlannerCustomDrill;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: delete one of my own custom drills (soft delete). Only the author can.
 */
class DeleteCustomDrill extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $drill = PlannerCustomDrill::where('id', $id)
                ->where('created_by', Auth::id())
                ->first();

            if ($drill) {
                $drill->delete();
            }

            return response()->json([
                'code'    => '098',
                'message' => 'custom drill deleted',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('DeleteCustomDrill: ' . $e->getMessage());

            return response()->json([
                'code'    => '098-E',
                'message' => 'failed to delete custom drill',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
