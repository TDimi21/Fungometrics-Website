<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataHub\RapsodoPitchingSessionReportResource;
use App\Models\CoachTeam;
use App\Services\Access\AdministrativeAccess;
use App\Services\Rapsodo\RapsodoPitchingSessionReportService;
use App\Services\Rapsodo\RapsodoReportException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RapsodoPitchingSessionReportController extends Controller
{
    public function __invoke(Request $request, string $batch, RapsodoPitchingSessionReportService $reports): JsonResponse
    {
        $data = $request->validate(['player_id' => ['nullable', 'uuid']]);
        try {
            $user = $request->user();
            $base = DB::table('import_batches as batch')
                ->join('translation_snapshots as snapshot', 'snapshot.id', '=', 'batch.translation_snapshot_id')
                ->where('batch.id', $batch)
                ->first(['snapshot.team_id']);
            if (!$base) {
                throw new RapsodoReportException('report_not_found', 'The requested Rapsodo report was not found.', 404);
            }
            $mappedPlayerIds = DB::table('external_sessions')->where('import_batch_id', $batch)
                ->whereNotNull('player_id')->distinct()->pluck('player_id')->map(fn ($id): string => (string) $id)->all();
            $requestedPlayerId = $data['player_id'] ?? null;
            if ('player' === (string) $user->type) {
                $authorized = in_array((string) $user->id, $mappedPlayerIds, true)
                    && (null === $requestedPlayerId || (string) $user->id === $requestedPlayerId);
            } else {
                $authorized = app(AdministrativeAccess::class)->canManageSubscriptions($user)
                    || CoachTeam::query()->where('coach_id', $user->id)->where('team_id', $base->team_id)->exists();
            }
            if (!$authorized) {
                throw new RapsodoReportException('unauthorized', 'You are not authorized to view this player report.', 403);
            }
            $scope = $reports->scope($batch, $requestedPlayerId);
            $report = $reports->report($batch, $scope['player_id']);
            unset($report['_scope']);

            return response()->json([
                'success' => true,
                'data' => RapsodoPitchingSessionReportResource::make($report)->resolve($request),
            ]);
        } catch (RapsodoReportException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->reportCode,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        }
    }
}
