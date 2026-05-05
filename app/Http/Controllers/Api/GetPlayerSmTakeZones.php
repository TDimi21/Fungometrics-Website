<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BullpenPracticeResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerSmTakeZones extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $player = $request->player;

            $pitches = BullpenPracticeResult::where('pitcher_id', $player)
                ->whereNotNull('pitch_mark')
                ->whereIn('trajectory', ['SM', 'TK'])
                ->select('pitch_mark', 'trajectory', 'type_throw')
                ->get()
                ->map(fn($p) => [
                    'pitch_mark'  => (int) $p->pitch_mark,
                    'trajectory'  => $p->trajectory,
                    'type_throw'  => $p->type_throw,
                ]);

            return response()->json([
                'code'   => '200',
                'status' => 'success',
                'data'   => $pitches,
            ], HttpCodes::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => '500',
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
