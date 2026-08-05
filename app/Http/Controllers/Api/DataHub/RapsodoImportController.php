<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Persistence\RapsodoImportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class RapsodoImportController extends Controller
{
    public function store(Request $request, RapsodoImportService $imports): JsonResponse
    {
        $maxKb = (int) ceil(((int) config('data_hub.max_file_size_bytes')) / 1024);
        $data = $request->validate([
            'platform' => ['required', Rule::in(['rapsodo'])],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'player_id' => ['required', 'uuid', 'exists:users,id'],
            'destination' => ['required', Rule::in(['bullpen', 'pitching_practice', 'assessment'])],
            'template_fingerprint' => ['required', 'string', 'size:64'],
            'file' => ['required', 'file', 'mimes:xlsx', "max:{$maxKb}"],
        ]);
        $this->authorizeTeam($request, $data['team_id']);
        abort_unless(DB::table('player_teams')->where('team_id', $data['team_id'])->where('user_id', $data['player_id'])
            ->where('actual', true)->whereNull('deleted_at')->exists(), 422, 'Choose a current player from this team.');

        try {
            $result = $imports->import(
                $request->user(), $data['team_id'], $data['player_id'], $data['destination'],
                $data['template_fingerprint'], $request->file('file')
            );

            return response()->json(['success' => true, 'message' => 'Rapsodo data imported successfully.', 'data' => $result], 201);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'The approved Rapsodo mapping is unavailable. Inspect and approve the file again.',
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false, 'message' => $exception->getMessage(),
            ], str_contains($exception->getMessage(), 'already been imported') ? 409 : 422);
        }
    }

    private function authorizeTeam(Request $request, string $teamId): void
    {
        abort_unless(app(AdministrativeAccess::class)->canManageSubscriptions($request->user())
            || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(), 403);
    }
}
