<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__).'/tests/Fixtures/DataHub';
$players = ['Thomas Dimitroff', 'Carter Moon', 'Brayden Jones', 'Jake Smith'];
$fixedDate = '2026-07-01';

/** @param array<int, array<int, string|int|float|null>> $rows */
function delimited(string $path, array $rows, string $delimiter = ','): void
{
    if ( ! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    $handle = fopen($path, 'wb');
    foreach ($rows as $row) {
        fputcsv($handle, $row, $delimiter);
    }
    fclose($handle);
}

function columnName(int $index): string
{
    $name = '';
    for (++$index; $index > 0; $index = intdiv($index - 1, 26)) {
        $name = chr(65 + (($index - 1) % 26)).$name;
    }

    return $name;
}

/**
 * @param array<int, array{name: string, rows: array<int, array<int, string|int|float|null>>, hidden?: bool, merges?: array<int, string>, formulas?: array<string, string>}> $sheets
 */
function xlsx(string $path, array $sheets): void
{
    if ( ! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $overrides = '';
    $workbookSheets = '';
    $relationships = '';
    foreach ($sheets as $sheetIndex => $sheet) {
        $number = $sheetIndex + 1;
        $overrides .= '<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $state = ($sheet['hidden'] ?? false) ? ' state="hidden"' : '';
        $workbookSheets .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1).'" sheetId="'.$number.'"'.$state.' r:id="rId'.$number.'"/>';
        $relationships .= '<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
        $xmlRows = [];
        foreach ($sheet['rows'] as $rowIndex => $values) {
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                if (null === $value) {
                    continue;
                }
                $reference = columnName($columnIndex).($rowIndex + 1);
                if (isset($sheet['formulas'][$reference])) {
                    $cells[] = '<c r="'.$reference.'"><f>'.htmlspecialchars($sheet['formulas'][$reference], ENT_XML1).'</f><v>'.htmlspecialchars((string) $value, ENT_XML1).'</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $cells[] = '<c r="'.$reference.'"><v>'.$value.'</v></c>';
                } else {
                    $cells[] = '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
            }
            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }
        $mergeXml = '';
        if ( ! empty($sheet['merges'])) {
            $mergeXml = '<mergeCells count="'.count($sheet['merges']).'">'.implode('', array_map(
                fn (string $range): string => '<mergeCell ref="'.$range.'"/>',
                $sheet['merges']
            )).'</mergeCells>';
        }
        $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $xmlRows).'</sheetData>'.$mergeXml.'</worksheet>');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.$overrides.'</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbookSheets.'</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>');
    for ($index = 0; $index < $zip->numFiles; ++$index) {
        $zip->setMtimeIndex($index, 1785124800);
    }
    $zip->close();
}

/** @return array<string, mixed> */
function entry(
    string $id,
    string $filename,
    string $format,
    string $platform,
    string $destination,
    string $layout,
    int $physicalRows,
    int $logicalRecords,
    int $columns,
    int $players,
    array $metrics,
    array $options = [],
): array {
    return array_merge([
        'id' => $id,
        'filename' => $filename,
        'format' => $format,
        'intended_platform' => $platform,
        'intended_destination' => $destination,
        'expected_layout' => $layout,
        'expected_worksheet' => 'Spreadsheet',
        'expected_header_row' => 1,
        'expected_first_data_row' => 2,
        'expected_physical_row_count' => $physicalRows,
        'expected_logical_record_count' => $logicalRecords,
        'expected_column_count' => $columns,
        'expected_unique_source_players' => $players,
        'expected_eligible_players' => $players,
        'expected_not_importing_players' => 0,
        'expected_metric_headers' => $metrics,
        'expected_mapped_concepts' => [],
        'expected_unknown_concepts' => [],
        'expected_warnings' => ['Confirm the file structure before Player Mapping.'],
        'expected_transformations' => [],
        'expected_detection_confidence_category' => 'high',
        'expected_extraction_confidence_category' => 'high',
        'manual_file_structure_confirmation_required' => true,
        'session_level_player_mapping_required' => 'single_player_session' === $layout,
        'duplicate_player_confirmation_required' => false,
        'duplicate_concept_confirmation_required' => false,
        'approval_should_pass' => true,
        'expected_blocked_reason' => null,
    ], $options);
}

$fixtures = [];
$add = function (array $definition) use (&$fixtures): void {
    $fixtures[] = $definition;
};

$assessmentHeaders = ['Player', 'Assessment Date', 'Body Weight', 'Bench Press', 'Back Squat', 'Deadlift', 'Vertical Jump', 'Broad Jump', '10 Yard Sprint', '40 Yard Sprint', 'Grip Strength', 'Notes'];
$assessmentRows = [$assessmentHeaders];
foreach ($players as $index => $player) {
    $assessmentRows[] = [$player, '2026-07-0'.($index + 1), 170 + $index * 10, 185 + $index * 10, 275 + $index * 15, 315 + $index * 15, 27 + $index, 105 + $index * 2, 1.75 - $index * .02, 5.05 - $index * .03, 92 + $index, 'Certification player'];
}
delimited($root.'/generic/assessment/assessment_players_in_rows.csv', $assessmentRows);
$add(entry('generic-assessment-rows', 'generic/assessment/assessment_players_in_rows.csv', 'csv', 'generic-csv', 'assessment', 'players_in_rows', 5, 4, 12, 4, array_slice($assessmentHeaders, 1), [
    'expected_mapped_concepts' => ['body_composition.body_weight', 'strength.bench_press', 'strength.back_squat', 'strength.deadlift', 'strength.vertical_jump', 'strength.broad_jump', 'speed_agility.sprint_10yd', 'speed_agility.sprint_40yd'],
]));

$strengthHeaders = ['Player', 'Test Date', 'Body Weight', 'Bench Press', 'Front Squat', 'Back Squat', 'Deadlift', 'Power Clean', 'Pull-Ups', 'Push-Ups', 'Grip Strength', 'Vertical Jump', 'Broad Jump', 'Medicine Ball Rotational Throw'];
$strengthRows = [$strengthHeaders];
foreach ($players as $index => $player) {
    $strengthRows[] = [$player, $fixedDate, 170 + $index * 10, 185 + $index * 10, 235 + $index * 10, 275 + $index * 15, 315 + $index * 15, 165 + $index * 10, 8 + $index, 30 + $index * 2, 92 + $index, 27 + $index, 105 + $index * 2, 34 + $index];
}
xlsx($root.'/generic/strength/strength_multi_player.xlsx', [['name' => 'Strength Testing', 'rows' => $strengthRows]]);
$add(entry('generic-strength-xlsx', 'generic/strength/strength_multi_player.xlsx', 'xlsx', 'generic-csv', 'strength', 'players_in_rows', 5, 4, 14, 4, array_slice($strengthHeaders, 1), ['expected_worksheet' => 'Strength Testing']));

$mobility = [
    ['Player', 'Assessment Date', 'Hip Mobility', 'Shoulder Mobility', 'Ankle Mobility', 'Hip Flexor Mobility', 'Rotational Mobility', 'Notes'],
    ['Thomas Dimitroff', $fixedDate, 4, 4, 3, 4, 4, 'Within range'],
    ['Carter Moon', $fixedDate, 3, 4, 4, 3, 4, 'Within range'],
    ['Brayden Jones', $fixedDate, 4, 9, 3, 4, 3, 'Shoulder score requires review'],
];
delimited($root.'/generic/mobility/mobility_multi_player.csv', $mobility);
$add(entry('generic-mobility-warning', 'generic/mobility/mobility_multi_player.csv', 'csv', 'generic-csv', 'mobility', 'players_in_rows', 4, 3, 8, 3, array_slice($mobility[0], 1), ['expected_warnings' => ['Shoulder Mobility value 9 is outside the configured ordinal range and requires review.']]));

$recovery = [
    ['Player', 'Date', 'Sleep Hours', 'Sleep Quality', 'Recovery Score', 'Mobility Score', 'Strength Score', 'Notes'],
    ['Thomas Dimitroff', $fixedDate, 8.0, 5, 88, 82, 86, 'Ready'],
    ['Carter Moon', $fixedDate, 6.5, 8, 72, '', 78, 'Sleep quality requires review'],
    ['Brayden Jones', $fixedDate, 7.5, 4, 80, 79, 81, 'Normal'],
];
delimited($root.'/generic/recovery/recovery_multi_player.tsv', $recovery, "\t");
$add(entry('generic-recovery-tsv', 'generic/recovery/recovery_multi_player.tsv', 'tsv', 'generic-csv', 'recovery', 'players_in_rows', 4, 3, 8, 3, array_slice($recovery[0], 1), ['expected_warnings' => ['Sleep Quality value 8 requires review; the raw value is preserved.']]));

$eventDefinitions = [
    ['generic-exit-velocity-events', 'generic/exit_velocity/exit_velocity_events.csv', 'exit_velocity', ['Player', 'Date', 'Swing Number', 'Exit Velocity', 'Launch Angle', 'Spray Angle', 'Projected Distance', 'Hit Trajectory', 'Notes'], 12],
    ['generic-long-toss-events', 'generic/long_toss/long_toss_events.csv', 'long_toss', ['Player', 'Date', 'Throw Number', 'Distance', 'Velocity', 'Hop Count', 'Notes'], 12],
];
foreach ($eventDefinitions as [$id, $filename, $destination, $headers, $count]) {
    $rows = [$headers];
    for ($index = 1; $index <= $count; ++$index) {
        $player = $players[$index % 3];
        $rows[] = 'exit_velocity' === $destination
            ? [$player, $fixedDate, $index, 82 + $index, -5 + $index * 2, -18 + $index * 3, 180 + $index * 8, $index < 3 ? 'Ground Ball' : 'Line Drive', '']
            : [$player, $fixedDate, $index, 120 + $index * 5, 70 + $index, $index % 3, ''];
    }
    delimited($root.'/'.$filename, $rows);
    $add(entry($id, $filename, 'csv', 'generic-csv', $destination, 'events_in_rows', $count + 1, $count, count($headers), 3, array_slice($headers, 1)));
}

$weightedHeaders = ['Player', 'Date', 'Throw Number', 'Ball Weight', 'Throw Type', 'Velocity', 'Notes'];
$weightedRows = [$weightedHeaders];
for ($index = 1; $index <= 12; ++$index) {
    $weightedRows[] = [$players[$index % 3], $fixedDate, $index, [3, 5, 7][$index % 3].' oz', 12 === $index ? 'Mystery Throw' : ['Pivot Pickoff', 'Rocker', 'Roll-In'][$index % 3], 72 + $index, ''];
}
xlsx($root.'/generic/weighted_ball/weighted_ball_events.xlsx', [['name' => 'Weighted Ball', 'rows' => $weightedRows]]);
$add(entry('generic-weighted-ball-events', 'generic/weighted_ball/weighted_ball_events.xlsx', 'xlsx', 'generic-csv', 'weighted_balls', 'events_in_rows', 13, 12, 7, 3, array_slice($weightedHeaders, 1), ['expected_worksheet' => 'Weighted Ball', 'expected_warnings' => ['Throw Type "Mystery Throw" requires controlled-value review.']]));

$matrix = [['Metric', 'Thomas Dimitroff', 'Carter Moon', 'Brayden Jones'], ['Body Weight', 180, 175, 190], ['Bench Press', 205, 195, 215], ['Back Squat', 315, 295, 335], ['Vertical Jump', 29, 28, 30]];
xlsx($root.'/generic/layouts/players_across_columns.xlsx', [['name' => 'Testing Matrix', 'rows' => $matrix]]);
$add(entry('layout-players-columns', 'generic/layouts/players_across_columns.xlsx', 'xlsx', 'generic-csv', 'assessment', 'players_in_columns', 5, 4, 4, 3, array_column(array_slice($matrix, 1), 0), ['expected_worksheet' => 'Testing Matrix', 'expected_transformations' => ['logical_transposition']]));

$sheetRows = [['Date', 'Metric', 'Value', 'Unit', 'Notes'], [$fixedDate, 'Bench Press', 185, 'lbs', ''], [$fixedDate, 'Back Squat', 275, 'lbs', ''], [$fixedDate, 'Vertical Jump', 28, 'in', '']];
xlsx($root.'/generic/layouts/worksheet_per_player.xlsx', array_map(fn (string $name): array => ['name' => $name, 'rows' => $sheetRows], array_slice($players, 0, 3)));
$add(entry('layout-worksheet-player', 'generic/layouts/worksheet_per_player.xlsx', 'xlsx', 'generic-csv', 'assessment', 'worksheet_per_player', 4, 3, 5, 3, ['Date', 'Metric', 'Value', 'Unit', 'Notes'], ['expected_worksheet' => 'Thomas Dimitroff', 'session_level_player_mapping_required' => true, 'expected_warnings' => ['Worksheet names are only player-identity candidates and require coach confirmation.']]));

$bullpen = [['Pitch Number', 'Velocity', 'Spin Rate', 'Induced Vertical Break', 'Horizontal Break', 'Strike']];
for ($i = 1; $i <= 10; ++$i) {
    $bullpen[] = [$i, 87 + $i / 2, 2200 + $i * 10, 15 + $i / 10, -8 + $i / 10, $i % 3 ? 'Yes' : 'No'];
}
delimited($root.'/generic/layouts/single_player_bullpen.csv', $bullpen);
$add(entry('layout-single-player', 'generic/layouts/single_player_bullpen.csv', 'csv', 'generic-csv', 'bullpen', 'single_player_session', 11, 10, 6, 1, $bullpen[0], ['session_level_player_mapping_required' => true]));

$multi = [['Player', 'Date', 'Event Number', 'Ball Weight', 'Velocity']];
foreach ([['Thomas Dimitroff', 10], ['Carter Moon', 8], ['Jake Smith', 6]] as [$name, $count]) {
    for ($i = 1; $i <= $count; ++$i) {
        $multi[] = [$name, $fixedDate, $i, '5 oz', 75 + $i];
    }
}
delimited($root.'/generic/layouts/multiple_player_events.csv', $multi);
$add(entry('layout-multiple-player-events', 'generic/layouts/multiple_player_events.csv', 'csv', 'generic-csv', 'weighted_balls', 'events_in_rows', 25, 24, 5, 3, array_slice($multi[0], 1), ['expected_eligible_players' => 2, 'expected_not_importing_players' => 1]));

$metadata = [['School', 'Certification Academy'], ['Coach', 'Fictional Coach'], ['Report', 'Strength Assessment'], ['Date Range', 'July 1-31 2026'], [], ['Player', 'Bench Press', 'Back Squat', 'Deadlift'], ['Thomas Dimitroff', 185, 275, 315], ['Carter Moon', 195, 295, 335]];
delimited($root.'/generic/layouts/metadata_before_header.csv', $metadata);
$add(entry('layout-metadata-header', 'generic/layouts/metadata_before_header.csv', 'csv', 'generic-csv', 'strength', 'players_in_rows', 8, 2, 4, 2, array_slice($metadata[5], 1), ['expected_header_row' => 6, 'expected_first_data_row' => 7, 'expected_transformations' => ['metadata_rows_excluded']]));

$blank = [[], [], ['Player', 'Bench Press', 'Back Squat', ''], ['Thomas Dimitroff', 185, 275, ''], [], ['Carter Moon', 195, 295, ''], [], []];
delimited($root.'/generic/layouts/blank_rows.csv', $blank);
$add(entry('layout-blank-rows', 'generic/layouts/blank_rows.csv', 'csv', 'generic-csv', 'strength', 'players_in_rows', 8, 2, 4, 2, ['Bench Press', 'Back Squat', 'Column 4'], ['expected_header_row' => 3, 'expected_first_data_row' => 4, 'expected_warnings' => ['Blank rows are ignored.', 'The fully empty column should be marked Not Importing.']]));

$simpleFixtures = [
    ['column-unknowns', 'generic/column_mapping/unknown_columns.csv', 'assessment', [['Player', 'Date', 'Super Score', 'XYZ Rating', 'ABC Efficiency'], ['Thomas Dimitroff', $fixedDate, 88, 7, 0.82], ['Carter Moon', $fixedDate, 84, 8, 0.79]], ['Super Score', 'XYZ Rating', 'ABC Efficiency']],
    ['player-duplicate-names', 'generic/player_mapping/duplicate_source_names.csv', 'assessment', [['Player', 'Assessment Date', 'Bench Press'], ['Tom Dimitroff', $fixedDate, 185], ['Thomas Dimitroff', $fixedDate, 190], ['Tommy Dimitroff', $fixedDate, 188]], []],
    ['player-no-roster', 'generic/player_mapping/no_roster_matches.csv', 'assessment', [['Player', 'Assessment Date', 'Bench Press'], ['Jake Smith', $fixedDate, 175], ['Ryan Jones', $fixedDate, 180], ['Logan Smith', $fixedDate, 185]], []],
    ['player-mixed-roster', 'generic/player_mapping/mixed_roster.csv', 'assessment', [['Player', 'Assessment Date', 'Bench Press'], ['Thomas Dimitroff', $fixedDate, 185], ['Carter Moon', $fixedDate, 195], ['Jake Smith', $fixedDate, 175], ['Ryan Jones', $fixedDate, 180]], []],
    ['column-manual-baseball', 'generic/column_mapping/manual_baseball_headers.csv', 'cage', [['Athlete', 'Test Day', 'Ball Speed', 'Angle', 'Direction', 'Distance'], ['Thomas Dimitroff', $fixedDate, 92, 24, -8, 340], ['Carter Moon', $fixedDate, 88, 18, 12, 305]], ['Angle', 'Direction', 'Distance']],
    ['column-duplicate-concepts', 'generic/column_mapping/duplicate_concepts.csv', 'cage', [['Player', 'ExitSpeed', 'Ball Speed', 'EV'], ['Thomas Dimitroff', 92, 91.8, 92.1], ['Carter Moon', 88, 87.5, 88.2]], []],
];
foreach ($simpleFixtures as [$id, $filename, $destination, $rows, $unknowns]) {
    delimited($root.'/'.$filename, $rows);
    $options = ['expected_unknown_concepts' => $unknowns];
    if ('column-unknowns' === $id) {
        $options['approval_should_pass'] = false;
        $options['expected_blocked_reason'] = 'At least one valid performance concept must be mapped.';
    }
    if ('player-duplicate-names' === $id) {
        $options['duplicate_player_confirmation_required'] = true;
    }
    if ('player-no-roster' === $id) {
        $options['expected_eligible_players'] = 0;
        $options['expected_not_importing_players'] = 3;
        $options['approval_should_pass'] = false;
        $options['expected_blocked_reason'] = 'At least one source player must be explicitly connected.';
    }
    if ('player-mixed-roster' === $id) {
        $options['expected_eligible_players'] = 2;
        $options['expected_not_importing_players'] = 2;
    }
    if ('column-duplicate-concepts' === $id) {
        $options['duplicate_concept_confirmation_required'] = true;
        $options['approval_should_pass'] = false;
        $options['expected_blocked_reason'] = 'Duplicate concept mappings require explicit confirmation or Not Importing decisions.';
    }
    $add(entry($id, $filename, 'csv', 'generic-csv', $destination, 'players_in_rows', count($rows), count($rows) - 1, count($rows[0]), count($rows) - 1, array_slice($rows[0], 1), $options));
}

$twenty = [['Player', 'Date', 'Event Number', 'Exit Velocity']];
for ($i = 1; $i <= 20; ++$i) {
    $twenty[] = ['Thomas Dimitroff', $fixedDate, $i, 80 + $i / 2];
}
delimited($root.'/generic/player_mapping/same_player_twenty_events.csv', $twenty);
$add(entry('player-twenty-events', 'generic/player_mapping/same_player_twenty_events.csv', 'csv', 'generic-csv', 'exit_velocity', 'events_in_rows', 21, 20, 4, 1, array_slice($twenty[0], 1)));

$fmtrxTemplate = (new \App\Services\DataHub\Templates\FmtrxTemplateCatalog())->get('strength');
$fmtrxKeys = array_column($fmtrxTemplate['fields'], 'key');
$fmtrxLabels = array_map(function (array $field): string {
    $details = array_filter([
        $field['unit'] ?? null,
        $field['required'] ? 'required' : null,
        null !== $field['min'] ? "min {$field['min']}" : null,
        null !== $field['max'] ? "max {$field['max']}" : null,
        $field['values'] ? implode('|', $field['values']) : null,
    ]);

    return $field['label'].($details ? ' ('.implode('; ', $details).')' : '');
}, $fmtrxTemplate['fields']);
$fmtrxRows = [['FMTRX_TEMPLATE', 'strength', 'VERSION', '1.0'], $fmtrxKeys, $fmtrxLabels];
foreach (array_slice($players, 0, 4) as $index => $player) {
    $values = [
        'fmtrx_player_id' => '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT),
        'player_name' => $player,
        'team_id' => '00000000-0000-4000-8000-000000000100',
        'record_date' => $fixedDate,
        'body_weight_lbs' => 170 + $index * 10,
        'front_squat_lbs' => 235 + $index * 10,
        'back_squat_lbs' => 275 + $index * 15,
        'bench_press_lbs' => 185 + $index * 10,
        'dead_lift_lbs' => 315 + $index * 15,
        'power_clean_lbs' => 165 + $index * 10,
        'pull_ups' => 8 + $index,
        'push_ups' => 30 + $index * 2,
        'grip_strength_left' => 90 + $index,
        'grip_strength_right' => 92 + $index,
        'vertical_jump_inches' => 27 + $index,
        'broad_jump_inches' => 105 + $index * 2,
        'med_ball_rotational_throw_ft' => 32 + $index,
        'sprint_10yd_sec' => 1.75 - $index * .02,
        'yd_40_dash_sec' => 5.05 - $index * .03,
        'yd_60_dash_sec' => 7.25 - $index * .03,
        'notes' => 'Certification player',
    ];
    $fmtrxRows[] = array_map(fn (string $key): mixed => $values[$key] ?? '', $fmtrxKeys);
}
delimited($root.'/generic/assessment/fmtrx_assessment_template.csv', $fmtrxRows);
$add(entry('fmtrx-generated-template', 'generic/assessment/fmtrx_assessment_template.csv', 'csv', 'fmtrx-template', 'strength', 'players_in_rows', 7, 4, count($fmtrxKeys), 4, array_slice($fmtrxKeys, 4), ['expected_header_row' => 3, 'expected_first_data_row' => 4, 'manual_file_structure_confirmation_required' => false, 'expected_warnings' => []]));
$altered = $fmtrxRows;
$altered[2][5] = 'Custom Bench Result';
$altered[1][5] = '';
delimited($root.'/generic/assessment/fmtrx_assessment_template_altered.csv', $altered);
$add(entry('fmtrx-altered-template', 'generic/assessment/fmtrx_assessment_template_altered.csv', 'csv', 'fmtrx-template', 'strength', 'players_in_rows', 7, 4, count($fmtrxKeys), 4, array_slice($fmtrxKeys, 4), ['approval_should_pass' => false, 'expected_blocked_reason' => 'FMTRX canonical-key row was altered.', 'expected_warnings' => ['The FMTRX template columns were changed or are incomplete.']]));

$badRows = [['Certification Assessment', '', '', ''], ['Player', 'Score', 'Score', ''], ['Thomas Dimitroff', '2026-07-01', 88, 'text'], ['Carter Moon', '07/02/2026', 'N/A', 5]];
xlsx($root.'/generic/invalid/bad_spreadsheet.xlsx', [
    ['name' => 'Visible Problems', 'rows' => $badRows, 'merges' => ['A1:B1'], 'formulas' => ['D4' => '1+1']],
    ['name' => 'Hidden Notes', 'rows' => [['Do not import'], ['Safe hidden worksheet']], 'hidden' => true],
]);
$add(entry('invalid-bad-xlsx', 'generic/invalid/bad_spreadsheet.xlsx', 'xlsx', 'generic-csv', 'assessment', 'worksheet_per_player', 4, 2, 4, 2, ['Score', 'Score', 'Column 4'], ['expected_worksheet' => 'Visible Problems', 'approval_should_pass' => false, 'expected_blocked_reason' => 'Ambiguous structure and columns must be corrected or ignored.', 'expected_warnings' => ['Merged cells exist and require review.', 'Formula cells were not executed; only cached displayed values were inspected.']]));

file_put_contents($root.'/generic/invalid/unsupported.txt', "Harmless unsupported Data Hub certification placeholder.\n");
file_put_contents($root.'/generic/invalid/malformed.csv', "\"unterminated,value\n");
file_put_contents($root.'/generic/invalid/malformed.xlsx', "This is intentionally not an XLSX package.\n");
foreach ([
    ['invalid-extension', 'generic/invalid/unsupported.txt', 'txt', 'Unsupported extension.'],
    ['invalid-malformed-csv', 'generic/invalid/malformed.csv', 'csv', 'Malformed CSV must not crash inspection.'],
    ['invalid-malformed-xlsx', 'generic/invalid/malformed.xlsx', 'xlsx', 'The Excel workbook could not be opened.'],
] as [$id, $filename, $format, $reason]) {
    $add(entry($id, $filename, $format, 'generic-csv', 'assessment', 'unknown', 0, 0, 0, 0, [], ['approval_should_pass' => false, 'expected_blocked_reason' => $reason, 'expected_warnings' => [$reason], 'expected_detection_confidence_category' => 'none', 'expected_extraction_confidence_category' => 'none']));
}

$trackman = [['Batter', 'Pitcher', 'Date', 'PitchUID', 'ExitSpeed', 'Angle', 'Direction', 'RelSpeed', 'SpinRate'], ['Thomas Dimitroff', 'Carter Moon', $fixedDate, 'tm-001', 92.4, 24.1, -7.2, 88.7, 2280], ['Carter Moon', 'Brayden Jones', $fixedDate, 'tm-002', 88.2, 18.4, 11.1, 90.1, 2360]];
delimited($root.'/platforms/trackman/trackman_mixed_sanitized.csv', $trackman);
$add(entry('platform-trackman', 'platforms/trackman/trackman_mixed_sanitized.csv', 'csv', 'trackman', 'live_ab', 'events_in_rows', 3, 2, 9, 3, array_slice($trackman[0], 2), ['manual_file_structure_confirmation_required' => false, 'expected_warnings' => []]));

$hittrax = [['#', 'AB', 'Date', 'Time Stamp', 'Pitch', 'Velo', 'LA', 'Dist', 'Type', 'Horiz. Angle', 'User'], [1, 1, $fixedDate, '10:00:00', 72, 92, 24, 340, 'Line Drive', -7, 'Thomas Dimitroff'], [2, 1, $fixedDate, '10:00:10', 74, 88, 18, 305, 'Fly Ball', 11, 'Carter Moon']];
delimited($root.'/platforms/hittrax/hittrax_hitting_sanitized.csv', $hittrax);
$add(entry('platform-hittrax', 'platforms/hittrax/hittrax_hitting_sanitized.csv', 'csv', 'hittrax', 'batting_practice', 'events_in_rows', 3, 2, 11, 2, array_slice($hittrax[0], 2), ['manual_file_structure_confirmation_required' => false, 'expected_warnings' => []]));

$rapsodoHeaders = ['no', 'time', 'pitch_type', 'velocity', 'spin_rate', 'true_spin', 'spin_eff', 'spin_direction', 'horz_break', 'vert_break', 'strike', 'rel_ht', 'rel_side', 'r_angle', 'h_angle', 'gyro'];
$rapsodoRows = [$rapsodoHeaders, [1, '10:00:00', '4S', 91.2, 2380, 2300, 96.6, '12h:45m', -8.2, 15.7, 'Y', 5.8, 2.1, -4.2, -2.1, 8.5]];
xlsx($root.'/platforms/rapsodo/rapsodo_pitching_sanitized.xlsx', [['name' => 'Rapsodo Session', 'rows' => $rapsodoRows]]);
$add(entry('platform-rapsodo', 'platforms/rapsodo/rapsodo_pitching_sanitized.xlsx', 'xlsx', 'rapsodo', 'bullpen', 'single_player_session', 2, 1, 16, 1, $rapsodoHeaders, ['expected_worksheet' => 'Rapsodo Session', 'manual_file_structure_confirmation_required' => false, 'session_level_player_mapping_required' => true, 'expected_warnings' => []]));

$blastHeaders = ['Date', 'Equipment', 'Handedness', 'Swing Details', 'Plane Score', 'Connection Score', 'Rotation Score', 'Bat Speed (mph)', 'Rotational Acceleration (g)', 'On Plane Efficiency (%)', 'Attack Angle (deg)', 'Early Connection (deg)', 'Connection at Impact (deg)', 'Vertical Bat Angle (deg)', 'Power (kW)', 'Time to Contact (sec)', 'Peak Hand Speed (mph)', 'Exit Velocity (mph)', 'Launch Angle (deg)', 'Estimated Distance (feet)'];
$blast = [['© Blast Motion. All rights reserved.'], ['NOTE: sanitized certification fixture'], ['Academy:', 'Certification Baseball Academy'], ['Report Date:', '07/01/2026'], ['Date Range:', '07/01/2026 - 07/01/2026'], ['Session:', 'Controlled Test'], [], $blastHeaders, ['July 1, 2026 / 10:00:00 AM', 'CERTIFICATION BBCOR', 'Right', 'General Practice', 46, 44, 49, 78.1, 9.5, 61, 12, 116, 96, -9, 5.66, .14, 21.9, '', '', '']];
delimited($root.'/platforms/blast/blast_motion_sanitized.csv', $blast);
$add(entry('platform-blast', 'platforms/blast/blast_motion_sanitized.csv', 'csv', 'blast-motion', 'batting_practice', 'single_player_session', 9, 1, 20, 1, $blastHeaders, ['expected_header_row' => 8, 'expected_first_data_row' => 9, 'manual_file_structure_confirmation_required' => false, 'session_level_player_mapping_required' => true, 'expected_warnings' => []]));

if ( ! is_dir($root.'/manifests')) {
    mkdir($root.'/manifests', 0775, true);
}
$manifest = [
    'schema_version' => '1.0',
    'generated_at' => '2026-07-27T00:00:00-04:00',
    'purpose' => 'FMTRX Data Hub inspection and mapping regression certification; never production import.',
    'test_roster' => ['Thomas Dimitroff', 'Carter Moon'],
    'fixtures' => $fixtures,
];
file_put_contents($root.'/manifests/import-certification.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
$lines = ['# FMTRX Data Hub Import Certification Manifest', '', 'Generated deterministically. All names and values are fictional test data.', '', '| ID | File | Format | Platform | Destination | Layout | Records | Players | Outcome |', '|---|---|---|---|---|---|---:|---:|---|'];
foreach ($fixtures as $fixture) {
    $outcome = $fixture['approval_should_pass'] ? (empty($fixture['expected_warnings']) ? 'Success' : 'Review / confirmation') : 'Blocked or rejected';
    $lines[] = sprintf('| `%s` | `%s` | %s | %s | %s | %s | %d | %d | %s |', $fixture['id'], $fixture['filename'], mb_strtoupper($fixture['format']), $fixture['intended_platform'], $fixture['intended_destination'], $fixture['expected_layout'], $fixture['expected_logical_record_count'], $fixture['expected_unique_source_players'], $outcome);
}
file_put_contents($root.'/manifests/import-certification.md', implode("\n", $lines)."\n");

$report = [
    '# FMTRX Data Hub Import Certification Results',
    '',
    'This suite is inspection-only. It creates no FMTRX practices, sessions, canonical events, assessments, statistics, profiles, mappings, or import batches.',
    '',
    '| Fixture | Format | Layout | Destination | Players | Metrics | Expected Outcome |',
    '|---|---|---|---|---:|---:|---|',
];
foreach ($fixtures as $fixture) {
    $outcome = $fixture['approval_should_pass']
        ? (empty($fixture['expected_warnings']) ? 'Expected success' : 'Expected review / manual confirmation')
        : ('Expected blocked/rejected: '.$fixture['expected_blocked_reason']);
    $report[] = sprintf(
        '| `%s` | %s | %s | %s | %d | %d | %s |',
        $fixture['id'],
        mb_strtoupper($fixture['format']),
        $fixture['expected_layout'],
        $fixture['intended_destination'],
        $fixture['expected_unique_source_players'],
        count($fixture['expected_metric_headers']),
        str_replace('|', '/', $outcome)
    );
}
$report = array_merge($report, [
    '',
    '## Generic layouts',
    '',
    'Players-in-rows, players-in-columns, event rows, worksheet-per-player, single-player sessions, metadata-before-header, blank-row handling, and logical transposition are covered. Source workbooks are never modified.',
    '',
    '## Player Mapping',
    '',
    'The suite covers repeated events, duplicate source names, no roster matches, mixed rosters, session-level assignment, Connected versus Not Importing counts, and duplicate-target confirmation.',
    '',
    '## Column Mapping',
    '',
    'Known concepts, unknown columns, context-sensitive baseball headers, duplicate concepts, controlled-value review, Store as Unknown, Submit New Concept, and approval blocking are represented in the manifest.',
    '',
    '## FMTRX templates',
    '',
    'The clean strength template is derived from the live `FmtrxTemplateCatalog` and its schema is compared to output from `FmtrxCsvTemplateService` in the certification test. An altered canonical-key row is expected to be rejected.',
    '',
    '## Known platforms',
    '',
    'Sanitized TrackMan, HitTrax, Rapsodo, and Blast Motion fixtures use the same headers and shapes exercised by their production inspection services. No private customer exports are copied.',
    '',
    '## Invalid and unsupported files',
    '',
    'A safe problematic workbook contains merged cells, duplicate/blank headers, mixed values, a non-executed formula, and a hidden worksheet. Harmless unsupported, malformed CSV, and malformed XLSX placeholders verify clear rejection without executable or malicious content.',
    '',
    '## Certification boundary',
    '',
    'Passing this suite certifies inspection and mapping workflow inputs. It does not certify Import Batch persistence or writes to canonical/legacy performance records.',
]);
$docsDirectory = dirname(__DIR__).'/docs/data-hub';
if ( ! is_dir($docsDirectory)) {
    mkdir($docsDirectory, 0775, true);
}
file_put_contents($docsDirectory.'/import-certification-results.md', implode("\n", $report)."\n");

$counts = array_count_values(array_column($fixtures, 'format'));
echo 'Generated '.count($fixtures).' certification entries: '
    .($counts['csv'] ?? 0).' CSV, '.($counts['tsv'] ?? 0).' TSV, '.($counts['xlsx'] ?? 0)." XLSX, and "
    .array_sum(array_diff_key($counts, array_flip(['csv', 'tsv', 'xlsx'])))." unsupported placeholders.\n";
