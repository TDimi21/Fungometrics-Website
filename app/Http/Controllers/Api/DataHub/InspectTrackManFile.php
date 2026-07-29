<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\Enums\ImportSessionType;
use App\Services\DataHub\Exceptions\TranslationFailureException;
use App\Services\DataHub\Generic\UniversalSpreadsheetInspector;
use App\Services\DataHub\Platforms\BlastMotion\BlastMotionInspectionService;
use App\Services\DataHub\Platforms\HitTrax\HitTraxInspectionService;
use App\Services\DataHub\Platforms\Rapsodo\RapsodoInspectionService;
use App\Services\DataHub\Platforms\TrackMan\TrackManInspectionService;
use App\Services\DataHub\Services\FmtrxDestination;
use App\Services\DataHub\Services\TranslationFailureCatalog;
use App\Services\DataHub\Templates\FmtrxTemplateInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

final class InspectTrackManFile extends Controller
{
    public function __invoke(Request $request, FmtrxDestination $destination, TrackManInspectionService $inspection, HitTraxInspectionService $hitTraxInspection, RapsodoInspectionService $rapsodoInspection, BlastMotionInspectionService $blastMotionInspection, FmtrxTemplateInspector $fmtrxTemplates, UniversalSpreadsheetInspector $genericInspection, TranslationFailureCatalog $failures): JsonResponse
    {
        $maxKb = (int) ceil(((int) config('data_hub.max_file_size_bytes')) / 1024);
        $data = $request->validate([
            'platform' => ['required', Rule::in(['trackman', 'hittrax', 'rapsodo', 'blast-motion', 'generic-csv'])],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'session_type' => ['required', Rule::enum(ImportSessionType::class)],
            'file' => ['required', 'file', "max:{$maxKb}"],
            'structure' => ['nullable', 'json'],
        ]);
        $file = $request->file('file');
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if ( ! in_array($extension, ['csv', 'xlsx', 'tsv'], true)) {
            $warning = $failures->warning('unsupported_file_type', ['extension' => $extension]);

            return response()->json([
                'success' => false,
                'code' => $warning['code'],
                'message' => $warning['message'],
                'warning' => $warning,
            ], 422);
        }
        if (0 === (int) $file->getSize()) {
            return response()->json(['success' => false, 'message' => 'The selected file is empty.'], 422);
        }
        $mime = mb_strtolower((string) $file->getMimeType());
        $allowedMimes = (array) config("data_hub.mime_types.{$extension}", []);
        if ('' !== $mime && 'application/octet-stream' !== $mime && ! in_array($mime, $allowedMimes, true)) {
            return response()->json(['success' => false, 'message' => 'The uploaded file content type is not valid for its extension.'], 422);
        }
        if ('xlsx' === $extension && ! in_array($data['platform'], ['rapsodo', 'generic-csv'], true)) {
            return response()->json(['success' => false, 'message' => 'XLSX inspection is awaiting approval of a maintained PHP spreadsheet reader. Export CSV to continue.'], 422);
        }
        if ('rapsodo' === $data['platform'] && 'xlsx' !== $extension) {
            return response()->json(['success' => false, 'message' => 'This Rapsodo pitching format requires an XLSX workbook.'], 422);
        }
        if ('tsv' === $extension && 'generic-csv' !== $data['platform']) {
            return response()->json(['success' => false, 'message' => 'TSV inspection is available through Generic Spreadsheet.'], 422);
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
            if ('generic-csv' === $data['platform']) {
                $structure = json_decode((string) ($data['structure'] ?? '{}'), true) ?: [];
                $result = 'csv' === $extension && $this->isFmtrxTemplate($metadata)
                    ? $fmtrxTemplates->inspect($metadata, (string) $team->id)
                    : $genericInspection->inspect($metadata, $structure);
                $result['destination_recommendation']['selected'] = $sessionType->label();

                return response()->json(['success' => true, 'data' => $result]);
            }
            if ('hittrax' === $data['platform']) {
                $result = $hitTraxInspection->inspect($metadata, (string) $team->id);
                $result['destination_recommendation']['selected'] = $sessionType->label();
                if ( ! in_array($sessionType, [ImportSessionType::BattingPractice, ImportSessionType::Cage], true)) {
                    $result['warnings'][] = 'Batting Practice or Cage is recommended for this HitTrax hitting export.';
                }

                return response()->json(['success' => true, 'data' => $result]);
            }
            if ('rapsodo' === $data['platform']) {
                $result = $rapsodoInspection->inspect($metadata);
                $result['destination_recommendation']['selected'] = $sessionType->label();
                if (ImportSessionType::Bullpen !== $sessionType) {
                    $result['warnings'][] = match ($sessionType) {
                        ImportSessionType::PitchingPractice => 'Pitching Practice is compatible, but Bullpen is recommended for this workbook.',
                        ImportSessionType::Assessment => 'Assessment requires confirmation because this workbook contains a pitching session.',
                        default => 'This destination is incompatible with the detected Rapsodo pitching workbook. Choose Bullpen or Pitching Practice.',
                    };
                }

                return response()->json(['success' => true, 'data' => $result]);
            }
            if ('blast-motion' === $data['platform']) {
                $result = $blastMotionInspection->inspect($metadata);
                $result['destination_recommendation']['selected'] = $sessionType->label();
                if (ImportSessionType::BattingPractice !== $sessionType) {
                    $result['warnings'][] = match ($sessionType) {
                        ImportSessionType::Cage => 'Cage is compatible, but Batting Practice is recommended for this Blast Motion report.',
                        ImportSessionType::Assessment => 'Assessment is compatible for baseline sensor measurements.',
                        default => 'This destination is incompatible with the detected Blast Motion swing-sensor report.',
                    };
                }

                return response()->json(['success' => true, 'data' => $result]);
            }
            $result = $inspection->inspect($metadata, (string) $team->id, $sessionType->value);
            $type = $result['detected_format']['data_type'];
            $compatible = match ($type) {
                'hitting' => in_array($sessionType, [ImportSessionType::Cage, ImportSessionType::LiveAb, ImportSessionType::BattingPractice], true),
                'pitching' => in_array($sessionType, [ImportSessionType::Bullpen, ImportSessionType::PitchingPractice, ImportSessionType::LiveAb], true),
                default => false,
            };
            if ( ! $compatible) {
                $recommended = match ($type) {
                    'hitting' => ['Cage', 'Batting Practice', 'Live AB'],
                    'pitching' => ['Bullpen', 'Pitching Practice', 'Live AB'],
                    default => [],
                };
                $result['warnings'][] = sprintf(
                    'The selected destination is allowed, but %s is recommended for this %s file.',
                    implode(', ', $recommended),
                    $type
                );
                $result['destination_recommendation'] = [
                    'detected_data_type' => $type,
                    'recommended' => $recommended,
                    'selected' => $sessionType->label(),
                    'advisory_only' => true,
                ];
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (TranslationFailureException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'warning' => $exception->warning(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } finally {
            Storage::disk('local')->delete($key);
        }
    }

    private function isFmtrxTemplate(ImportFileMetadata $file): bool
    {
        if ( ! $file->path || ! is_readable($file->path)) {
            return false;
        }
        $handle = fopen($file->path, 'rb');
        if (false === $handle) {
            return false;
        }
        try {
            $firstRow = fgetcsv($handle) ?: [];
            $marker = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) ($firstRow[0] ?? '')));

            return 'FMTRX_TEMPLATE' === $marker;
        } finally {
            fclose($handle);
        }
    }
}
