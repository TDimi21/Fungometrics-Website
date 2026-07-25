<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\Team;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Templates\FmtrxCsvTemplateService;
use App\Services\DataHub\Templates\FmtrxTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class FmtrxTemplateController extends Controller
{
    public function index(FmtrxTemplateCatalog $catalog): JsonResponse
    {
        $templates = collect($catalog->all())->sortBy('priority')->map(fn (array $template): array => [
            'key' => $template['key'],
            'label' => $template['label'],
            'version' => $template['version'],
            'priority' => $template['priority'],
            'field_count' => count($template['fields']),
            'sections' => array_values(array_unique(array_column($template['fields'], 'section'))),
        ])->values();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function download(Request $request, FmtrxTemplateCatalog $catalog, FmtrxCsvTemplateService $csv): Response
    {
        $keys = array_keys($catalog->all());
        $data = $request->validate([
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'template' => ['required', Rule::in($keys)],
        ]);
        $admin = app(AdministrativeAccess::class)->canManageSubscriptions($request->user());
        abort_unless($admin || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $data['team_id'])->exists(), 403);
        $team = Team::query()->findOrFail($data['team_id']);
        $filename = sprintf('fmtrx-%s-%s-v%s.csv', Str::slug($team->name), $data['template'], FmtrxTemplateCatalog::VERSION);

        return response($csv->generate($data['template'], (string) $team->id, (string) $team->name), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
