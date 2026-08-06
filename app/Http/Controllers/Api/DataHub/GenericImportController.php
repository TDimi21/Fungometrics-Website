<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Enums\ImportSessionType;
use App\Services\DataHub\Persistence\GenericImportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

final class GenericImportController extends Controller
{
    public function store(Request $request, GenericImportService $imports): JsonResponse
    {
        $maxKb = (int) ceil(((int) config('data_hub.max_file_size_bytes')) / 1024);
        $data = $request->validate([
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'destination' => ['required', Rule::enum(ImportSessionType::class)],
            'template_fingerprint' => ['required', 'string', 'size:64'],
            'structure' => ['required', 'json'],
            'player_mappings' => ['required', 'json'],
            'file' => ['required', 'file', "max:{$maxKb}"],
        ]);
        $this->authorizeTeam($request, $data['team_id']);
        $structure = json_decode((string) $data['structure'], true) ?: [];
        $playerMappings = json_decode((string) $data['player_mappings'], true) ?: [];

        try {
            $result = $imports->import(
                $request->user(),
                $data['team_id'],
                $data['destination'],
                $data['template_fingerprint'],
                $structure,
                $playerMappings,
                $request->file('file'),
            );

            return response()->json(['success' => true, 'message' => 'Spreadsheet imported successfully.', 'data' => $result], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], str_contains($exception->getMessage(), 'already been imported') ? 409 : 422);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'The approved column mapping is unavailable. Inspect and approve the file again.'], 422);
        }
    }

    private function authorizeTeam(Request $request, string $teamId): void
    {
        abort_unless(app(AdministrativeAccess::class)->canManageSubscriptions($request->user())
            || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(), 403);
    }
}
