<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$concepts = DB::table('baseball_concepts as concepts')
    ->join('baseball_domains as domains', 'domains.id', '=', 'concepts.domain_id')
    ->leftJoin('baseball_concept_aliases as aliases', 'aliases.baseball_concept_id', '=', 'concepts.id')
    ->select([
        'domains.name as domain',
        'concepts.id',
        'concepts.display_name',
        'concepts.canonical_key',
        'concepts.definition',
        'concepts.canonical_unit_key',
        'concepts.valid_min',
        'concepts.valid_max',
        'concepts.research_eligible',
        'concepts.profile_visible',
        DB::raw("GROUP_CONCAT(aliases.alias ORDER BY aliases.alias SEPARATOR ', ') as aliases"),
    ])
    ->groupBy([
        'domains.name',
        'concepts.id',
        'concepts.display_name',
        'concepts.canonical_key',
        'concepts.definition',
        'concepts.canonical_unit_key',
        'concepts.valid_min',
        'concepts.valid_max',
        'concepts.research_eligible',
        'concepts.profile_visible',
    ])
    ->orderBy('domains.name')
    ->orderBy('concepts.display_name')
    ->get();

$escape = static fn (mixed $value): string => str_replace('|', '\\|', trim((string) $value));
$lines = [
    '# FMTRX Baseball Dictionary Review',
    '',
    'Generated from the Phase 2B.1 seed on MariaDB 10.1.48. Review before canonical import storage begins.',
    '',
    '> Compatible sessions are not yet persisted as concept metadata in Phase 2B.1. The report marks them as “Not constrained” rather than inferring compatibility.',
    '',
    '| Domain | Concept | Canonical key | Definition | Unit | Valid range | Compatible sessions | TrackMan aliases | Research eligible | Profile visible |',
    '|---|---|---|---|---|---|---|---|---:|---:|',
];

foreach ($concepts as $concept) {
    $range = null === $concept->valid_min && null === $concept->valid_max
        ? 'Unbounded warning range'
        : ($concept->valid_min ?? '−∞').' to '.($concept->valid_max ?? '∞');
    $lines[] = sprintf(
        '| %s | %s | `%s` | %s | %s | %s | Not constrained | %s | %s | %s |',
        $escape($concept->domain),
        $escape($concept->display_name),
        $escape($concept->canonical_key),
        $escape($concept->definition),
        $escape($concept->canonical_unit_key ?: '—'),
        $escape($range),
        $escape($concept->aliases ?: '—'),
        $concept->research_eligible ? 'Yes' : 'No',
        $concept->profile_visible ? 'Yes' : 'No',
    );
}

$target = dirname(__DIR__).'/docs/data-hub/baseball-dictionary-review.md';
if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, implode(PHP_EOL, $lines).PHP_EOL);
fwrite(STDOUT, sprintf("Wrote %d concepts to %s\n", $concepts->count(), $target));
