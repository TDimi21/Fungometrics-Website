<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Enums\ImportSessionType;
use App\Services\DataHub\Platforms\TrackMan\TrackManInspectionService;
use App\Services\DataHub\Services\FmtrxDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

final class InspectTrackManFile extends Controller
{
    public function __invoke(Request $request, FmtrxDestination $destination, TrackManInspectionService $inspection): JsonResponse
    {
        $maxKb = (int) ceil(((int) config('data_hub.max_file_size_bytes')) / 1024);
        $data = $request->validate([
            'platform' => ['required', Rule::in(['trackman'])],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'session_type' => ['required', Rule::enum(ImportSessionType::class)],
            'file' => ['required', 'file', "max:{$maxKb}"],
        ]);
        $file = $request->file('file');
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if ( ! in_array($extension, ['csv', 'xlsx'], true)) {
            return response()->json(['success' => false, 'message' => 'Choose a TrackMan CSV or XLSX file.'], 422);
        }
        if (0 === (int) $file->getSize()) {
            return response()->json(['success' => false, 'message' => 'The selected file is empty.'], 422);
        }
        $mime = mb_strtolower((string) $file->getMimeType());
        $allowedMimes = (array) config("data_hub.mime_types.{$extension}", []);
        if ('' !== $mime && 'application/octet-stream' !== $mime && ! in_array($mime, $allowedMimes, true)) {
            return response()->json(['success' => false, 'message' => 'The uploaded file content type is not valid for its extension.'], 422);
        }
        if ('xlsx' === $extension) {
            return response()->json(['success' => false, 'message' => 'TrackMan XLSX inspection is awaiting approval of a maintained PHP spreadsheet reader. Export CSV to continue.'], 422);
        }
        $team = Team::query()->findOrFail($data['team_id']);
        $sessionType = ImportSessionType::from($data['session_type']);
        if ( ! $destination->validateDestination($request->user(), $team, $sessionType)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to inspect files for this team.'], 403);
        }
        $key = 'data-hub/tmp/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($key, file_get_contents($file->getRealPath()));
        try {
            $metadata = new ImportFileMetadata(
                basename((string) $file->getClientOriginalName()),
                (int) $file->getSize(),
                $extension,
                $file->getMimeType(),
                Storage::disk('local')->path($key),
            );
            $result = $inspection->inspect($metadata, (string) $team->id, $sessionType->value);
            $type = $result['detected_format']['data_type'];
            $compatible = match ($type) {
                'hitting' => in_array($sessionType, [ImportSessionType::Cage, ImportSessionType::LiveAb, ImportSessionType::BattingPractice], true),
                'pitching' => in_array($sessionType, [ImportSessionType::Bullpen, ImportSessionType::PitchingPractice, ImportSessionType::LiveAb], true),
                default => false,
            };
            if ( ! $compatible) {
                return response()->json([
                    'success' => false,
                    'message' => 'The detected TrackMan data is not compatible with the selected destination.',
                    'detected_format' => $result['detected_format'],
                ], 422);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } finally {
            Storage::disk('local')->delete($key);
        }
    }
}
