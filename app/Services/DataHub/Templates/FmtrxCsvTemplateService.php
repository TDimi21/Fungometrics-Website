<?php

declare(strict_types=1);

namespace App\Services\DataHub\Templates;

use App\Services\DataHub\Services\PlayerMatchingService;

final readonly class FmtrxCsvTemplateService
{
    public function __construct(
        private FmtrxTemplateCatalog $catalog,
        private PlayerMatchingService $players,
    ) {
    }

    public function generate(string $templateKey, string $teamId, string $teamName): string
    {
        $template = $this->catalog->get($templateKey);
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['FMTRX_TEMPLATE', $template['key'], 'VERSION', $template['version']]);
        fputcsv($handle, array_column($template['fields'], 'key'));
        fputcsv($handle, array_map(fn (array $field): string => $this->heading($field), $template['fields']));
        foreach ($this->players->roster($teamId) as $player) {
            $values = ['fmtrx_player_id' => $player['id'], 'player_name' => $this->safeCell($player['name']), 'team_id' => $teamId, 'record_date' => ''];
            fputcsv($handle, array_map(fn (array $field): string => (string) ($values[$field['key']] ?? ''), $template['fields']));
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$contents;
    }

    private function heading(array $field): string
    {
        $details = array_filter([
            $field['unit'] ?? null,
            $field['required'] ? 'required' : null,
            null !== $field['min'] ? "min {$field['min']}" : null,
            null !== $field['max'] ? "max {$field['max']}" : null,
            $field['values'] ? implode('|', $field['values']) : null,
        ]);

        return $field['label'].($details ? ' ('.implode('; ', $details).')' : '');
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value;
    }
}
