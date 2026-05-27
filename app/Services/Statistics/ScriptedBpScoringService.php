<?php

declare(strict_types=1);

namespace App\Services\Statistics;

/**
 * FMTRX Scripted BP Scoring Engine
 *
 * Computes a per-swing score based on round type, contact quality,
 * trajectory, direction, and exit velocity.
 *
 * Base scores by contact type:
 *   Barrel  = 10 | Hard = 8 | Average = 5 | Weak = 2 | Miss = 0
 *
 * Each round then applies bonuses/penalties on top of the base.
 * Final score is clamped to 0 (no negative totals).
 */
class ScriptedBpScoringService
{
    private const BASE_SCORES = [
        'Barrel'  => 10,
        'Hard'    => 8,
        'Average' => 5,
        'Weak'    => 2,
        'Miss'    => 0,
    ];

    /**
     * Score a single swing.
     *
     * @param  string      $roundType    e.g. 'BARREL'
     * @param  string      $contactType  Barrel | Hard | Average | Weak | Miss
     * @param  string|null $trajectory   LineDrive | FlyBall | GroundBall | PopUp | Foul
     * @param  string|null $direction    Pull | Middle | Oppo
     * @param  int|null    $exitVelocity mph
     * @return array{score: int, modifiers: array<array{label: string, delta: int}>}
     */
    public function score(
        string $roundType,
        string $contactType,
        ?string $trajectory,
        ?string $direction,
        ?int $exitVelocity,
    ): array {
        $base      = self::BASE_SCORES[$contactType] ?? 0;
        $modifiers = [];

        $delta = match ($roundType) {
            'BARREL'          => $this->barrelRound($contactType, $trajectory, $direction, $exitVelocity, $modifiers),
            'OPPO_GAP'        => $this->oppoGapRound($contactType, $trajectory, $direction, $modifiers),
            'PULL_DAMAGE'     => $this->pullDamageRound($contactType, $trajectory, $direction, $exitVelocity, $modifiers),
            'TWO_STRIKE'      => $this->twoStrikeRound($contactType, $trajectory, $direction, $modifiers),
            'SAC_FLY'         => $this->sacFlyRound($contactType, $trajectory, $direction, $modifiers),
            'HIT_AND_RUN'     => $this->hitAndRunRound($contactType, $trajectory, $direction, $modifiers),
            'LINE_DRIVE'      => $this->lineDriveRound($contactType, $trajectory, $modifiers),
            'HARD_CONTACT'    => $this->hardContactRound($contactType, $trajectory, $exitVelocity, $modifiers),
            'FASTBALL_HUNT'   => $this->fastballHuntRound($contactType, $trajectory, $direction, $modifiers),
            'OFFSPEED_ADJ'    => $this->offspeedAdjRound($contactType, $trajectory, $direction, $modifiers),
            'GAP_TO_GAP'      => $this->gapToGapRound($contactType, $direction, $modifiers),
            'INSIDE_PITCH'    => $this->insidePitchRound($contactType, $trajectory, $direction, $modifiers),
            'OUTSIDE_PITCH'   => $this->outsidePitchRound($contactType, $trajectory, $direction, $modifiers),
            'FIRST_PITCH_ATK' => $this->firstPitchAttackRound($contactType, $trajectory, $direction, $modifiers),
            'BEHIND_COUNT'    => $this->behindCountRound($contactType, $trajectory, $direction, $modifiers),
            'ADVANTAGE_COUNT' => $this->advantageCountRound($contactType, $trajectory, $modifiers),
            'OPPO_POWER'      => $this->oppoPowerRound($contactType, $trajectory, $direction, $modifiers),
            'CONTACT'         => $this->contactRound($contactType, $direction, $modifiers),
            'PRESSURE'        => $this->pressureRound($contactType, $trajectory, $direction, $modifiers),
            'CHAMPIONSHIP'    => $this->championshipRound($contactType, $trajectory, $direction, $exitVelocity, $modifiers),
            default           => 0,
        };

        return [
            'score'     => max(0, $base + $delta),
            'modifiers' => $modifiers,
        ];
    }

    /**
     * Derive a session grade (Elite → Needs Work) from an average swing score.
     *
     * @param  float $averageScore  average raw_score across all swings (0–12+)
     * @return string
     */
    public function grade(float $averageScore): string
    {
        // Normalise to 0–100 scale (max attainable swing ~14, typical range 0–12)
        $pct = min(100, ($averageScore / 12) * 100);

        return match (true) {
            $pct >= 95 => 'Elite',
            $pct >= 90 => 'Championship',
            $pct >= 80 => 'Winning',
            $pct >= 70 => 'Competitive',
            $pct >= 60 => 'Development',
            default    => 'Needs Work',
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Round scoring methods (private)
    // ─────────────────────────────────────────────────────────────────────────

    /** 1. BARREL ROUND — Hard line drives through middle */
    private function barrelRound(string $c, ?string $t, ?string $d, ?int $ev, array &$mods): int
    {
        $m = 0;
        if ($t === 'LineDrive' && in_array($d, ['Middle', 'Oppo'], true)) { $mods[] = ['label' => 'Line drive middle/oppo', 'delta' => 2]; $m += 2; }
        if ($ev !== null && $ev >= 95)                                     { $mods[] = ['label' => 'EV over target (95+)',   'delta' => 1]; $m += 1; }
        if ($t === 'LineDrive' && $d === 'Oppo')                           { $mods[] = ['label' => 'Oppo line drive',         'delta' => 1]; $m += 1; }
        if ($t === 'PopUp')                                                 { $mods[] = ['label' => 'Pop fly',                 'delta' => -3]; $m -= 3; }
        if ($t === 'GroundBall' && $d === 'Pull')                          { $mods[] = ['label' => 'Roll over',               'delta' => -2]; $m -= 2; }
        if ($c === 'Miss')                                                  { $mods[] = ['label' => 'Swing/Miss penalty',      'delta' => -5]; $m -= 5; }
        return $m;
    }

    /** 2. OPPO GAP ROUND — Drive baseball opposite field */
    private function oppoGapRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss' && $t === 'LineDrive' && $d === 'Oppo')         { $mods[] = ['label' => 'Hard oppo line drive',      'delta' => 3]; $m += 3; }
        if ($t === 'FlyBall' && $d === 'Oppo')                            { $mods[] = ['label' => 'Oppo gap fly ball',          'delta' => 2]; $m += 2; }
        if ($d === 'Oppo')                                                 { $mods[] = ['label' => 'Stay backside',              'delta' => 1]; $m += 1; }
        if ($t === 'GroundBall' && $d === 'Pull')                         { $mods[] = ['label' => 'Pull-side rollover',         'delta' => -3]; $m -= 3; }
        if (in_array($c, ['Weak'], true) && $d === 'Oppo')               { $mods[] = ['label' => 'Weak opposite contact',     'delta' => -1]; $m -= 1; }
        return $m;
    }

    /** 3. PULL-SIDE DAMAGE — Create pull-side power */
    private function pullDamageRound(string $c, ?string $t, ?string $d, ?int $ev, array &$mods): int
    {
        $m = 0;
        if ($c === 'Barrel' && $d === 'Pull')                             { $mods[] = ['label' => 'Pull-side barrel',          'delta' => 3]; $m += 3; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true) && $d === 'Pull') { $mods[] = ['label' => 'Extra-base trajectory',   'delta' => 2]; $m += 2; }
        if ($ev !== null && $ev >= 95)                                     { $mods[] = ['label' => 'EV threshold passed',       'delta' => 1]; $m += 1; }
        if ($t === 'GroundBall' && $d === 'Pull')                         { $mods[] = ['label' => 'Ground ball rollover',      'delta' => -3]; $m -= 3; }
        if (in_array($c, ['Weak', 'Average'], true) && $d === 'Pull' && $t !== 'LineDrive') { $mods[] = ['label' => 'Jam shot', 'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 4. TWO-STRIKE COMPETE — Compete without swing/miss */
    private function twoStrikeRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'Foul')                                                 { $mods[] = ['label' => 'Foul off tough pitch',     'delta' => 2]; $m += 2; }
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')     { $mods[] = ['label' => 'Middle/oppo contact',      'delta' => 2]; $m += 2; }
        if ($t === 'LineDrive')                                            { $mods[] = ['label' => 'Line drive',               'delta' => 3]; $m += 3; }
        if ($c === 'Miss')                                                 { $mods[] = ['label' => 'Swing/Miss',               'delta' => -5]; $m -= 5; }
        return $m;
    }

    /** 5. SAC FLY ROUND — Productive fly ball */
    private function sacFlyRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'FlyBall' && in_array($c, ['Barrel', 'Hard'], true))  { $mods[] = ['label' => 'Deep fly ball',            'delta' => 3]; $m += 3; }
        if ($t === 'FlyBall')                                              { $mods[] = ['label' => 'Sac fly trajectory',       'delta' => 3]; $m += 3; }
        if ($t === 'LineDrive' && in_array($c, ['Hard', 'Barrel'], true)){ $mods[] = ['label' => 'Hard line drive',          'delta' => 2]; $m += 2; }
        if ($t === 'GroundBall' && in_array($c, ['Weak', 'Average'], true)){ $mods[] = ['label' => 'Weak ground ball',       'delta' => -3]; $m -= 3; }
        if ($t === 'PopUp')                                                { $mods[] = ['label' => 'Pop up',                  'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 6. HIT-AND-RUN — Ground ball behind runner */
    private function hitAndRunRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'GroundBall' && in_array($d, ['Oppo', 'Middle'], true)){ $mods[] = ['label' => 'Ground ball oppo/middle', 'delta' => 3]; $m += 3; }
        if ($c !== 'Miss' && $t !== 'FlyBall')                            { $mods[] = ['label' => 'Productive contact',      'delta' => 2]; $m += 2; }
        if ($t === 'FlyBall' && $d === 'Pull')                            { $mods[] = ['label' => 'Pull-side fly ball',      'delta' => -3]; $m -= 3; }
        if ($c === 'Miss')                                                 { $mods[] = ['label' => 'Swing/Miss',              'delta' => -5]; $m -= 5; }
        return $m;
    }

    /** 7. LINE DRIVE ROUND — Only line drives score high */
    private function lineDriveRound(string $c, ?string $t, array &$mods): int
    {
        $m = 0;
        if ($t === 'LineDrive' && in_array($c, ['Barrel', 'Hard'], true)){ $mods[] = ['label' => 'Hard line drive',          'delta' => 4]; $m += 4; }
        if ($t === 'LineDrive')                                            { $mods[] = ['label' => 'Gap line drive',           'delta' => 2]; $m += 2; }
        if ($t === 'PopUp')                                                { $mods[] = ['label' => 'Pop up',                  'delta' => -4]; $m -= 4; }
        if ($t === 'GroundBall' && in_array($c, ['Weak', 'Average'], true)){ $mods[] = ['label' => 'Weak ground ball',       'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 8. HARD CONTACT ROUND — Highest EV possible */
    private function hardContactRound(string $c, ?string $t, ?int $ev, array &$mods): int
    {
        $m = 0;
        // Override base with EV-based score if EV provided
        if ($ev !== null) {
            $evScore = match (true) {
                $ev >= 100 => 10,
                $ev >= 95  => 8,
                $ev >= 88  => 5,
                default    => 2,
            };
            $mods[] = ['label' => "EV {$ev}mph score", 'delta' => $evScore - (self::BASE_SCORES[$c] ?? 0)];
            $m += $evScore - (self::BASE_SCORES[$c] ?? 0);
        }
        if ($t === 'LineDrive' && in_array($c, ['Barrel', 'Hard'], true)){ $mods[] = ['label' => 'Hard-hit line drive',      'delta' => 2]; $m += 2; }
        return $m;
    }

    /** 9. FASTBALL HUNT — Damage fastballs early */
    private function fastballHuntRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Barrel' && $t !== null)                               { $mods[] = ['label' => 'First-pitch barrel',      'delta' => 4]; $m += 4; }
        if ($t !== null && in_array($t, ['LineDrive', 'FlyBall'], true) && $d === 'Pull') { $mods[] = ['label' => 'Pull-side damage', 'delta' => 2]; $m += 2; }
        if (in_array($c, ['Weak', 'Average'], true) && $t === 'GroundBall') { $mods[] = ['label' => 'Late swing',           'delta' => -2]; $m -= 2; }
        if ($c === 'Miss')                                                 { $mods[] = ['label' => 'Take strike',            'delta' => -3]; $m -= 3; }
        return $m;
    }

    /** 10. OFFSPEED ADJUSTMENT — Stay back */
    private function offspeedAdjRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if (in_array($c, ['Barrel', 'Hard'], true) && $d === 'Oppo')     { $mods[] = ['label' => 'Hard offspeed contact',   'delta' => 4]; $m += 4; }
        if ($t === 'LineDrive' && $d === 'Oppo')                          { $mods[] = ['label' => 'Oppo line drive',         'delta' => 2]; $m += 2; }
        if ($t === 'GroundBall' && $d === 'Pull')                         { $mods[] = ['label' => 'Out front rollover',      'delta' => -3]; $m -= 3; }
        if ($c === 'Miss')                                                 { $mods[] = ['label' => 'Swing/Miss',              'delta' => -4]; $m -= 4; }
        return $m;
    }

    /** 11. GAP-TO-GAP — Use all fields */
    private function gapToGapRound(string $c, ?string $d, array &$mods): int
    {
        $m = 0;
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Gap contact',              'delta' => 3]; $m += 3; }
        if ($d === 'Oppo' && $c !== 'Miss')                              { $mods[] = ['label' => 'Opposite gap',             'delta' => 3]; $m += 3; }
        if ($d === 'Pull' && in_array($c, ['Weak', 'Average'], true))   { $mods[] = ['label' => 'Dead pull rollover',       'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 12. INSIDE PITCH ROUND — Handle velocity inside */
    private function insidePitchRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Pull' && in_array($c, ['Barrel', 'Hard'], true))    { $mods[] = ['label' => 'Turn on inside pitch',     'delta' => 4]; $m += 4; }
        if ($c === 'Barrel' && $d === 'Pull')                            { $mods[] = ['label' => 'Pull-side barrel',         'delta' => 3]; $m += 3; }
        if (in_array($c, ['Weak', 'Average'], true) && $t !== 'LineDrive') { $mods[] = ['label' => 'Jammed',               'delta' => -3]; $m -= 3; }
        if ($t === 'PopUp')                                              { $mods[] = ['label' => 'Weak popup',               'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 13. OUTSIDE PITCH ROUND — Drive outside pitch opposite */
    private function outsidePitchRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Barrel' && $d === 'Oppo')                           { $mods[] = ['label' => 'Oppo barrel',               'delta' => 4]; $m += 4; }
        if ($t === 'FlyBall' && $d === 'Oppo')                          { $mods[] = ['label' => 'Gap oppo fly ball',          'delta' => 3]; $m += 3; }
        if ($t === 'GroundBall' && $d === 'Pull')                       { $mods[] = ['label' => 'Roll over pull side',        'delta' => -4]; $m -= 4; }
        return $m;
    }

    /** 14. FIRST-PITCH ATTACK — Damage first pitch only */
    private function firstPitchAttackRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if (in_array($c, ['Hard', 'Barrel'], true))                     { $mods[] = ['label' => 'First-pitch hard contact',  'delta' => 5]; $m += 5; }
        if ($c === 'Barrel')                                             { $mods[] = ['label' => 'First-pitch barrel bonus',  'delta' => 1]; $m += 1; } // stacks: +6 total
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Passive take / miss',       'delta' => -4]; $m -= 4; }
        return $m;
    }

    /** 15. BEHIND-IN-COUNT — Battle */
    private function behindCountRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss' && $t !== null)                               { $mods[] = ['label' => 'Productive contact',        'delta' => 3]; $m += 3; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => '2-strike line drive',       'delta' => 4]; $m += 4; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Strikeout / Miss',          'delta' => -5]; $m -= 5; }
        return $m;
    }

    /** 16. ADVANTAGE COUNT — Punish mistakes */
    private function advantageCountRound(string $c, ?string $t, array &$mods): int
    {
        $m = 0;
        if (in_array($c, ['Barrel', 'Hard'], true))                     { $mods[] = ['label' => 'Damage contact',            'delta' => 5]; $m += 5; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true) && in_array($c, ['Barrel', 'Hard'], true)) { $mods[] = ['label' => 'Extra-base trajectory', 'delta' => 3]; $m += 3; }
        if (in_array($c, ['Weak', 'Average'], true))                    { $mods[] = ['label' => 'Weak contact in hitter count', 'delta' => -4]; $m -= 4; }
        return $m;
    }

    /** 17. OPPO POWER — Drive ball deep opposite field */
    private function oppoPowerRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'FlyBall' && $d === 'Oppo')                         { $mods[] = ['label' => 'Oppo fly ball',              'delta' => 3]; $m += 3; }
        if ($c === 'Barrel' && $d === 'Oppo')                          { $mods[] = ['label' => 'Oppo gap barrel',            'delta' => 5]; $m += 5; }
        if (in_array($c, ['Weak', 'Average'], true) && $d === 'Oppo') { $mods[] = ['label' => 'Weak opposite contact',     'delta' => -2]; $m -= 2; }
        return $m;
    }

    /** 18. CONTACT ROUND — No misses */
    private function contactRound(string $c, ?string $d, array &$mods): int
    {
        $m = 0;
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')  { $mods[] = ['label' => 'Middle/oppo contact',       'delta' => 2]; $m += 2; }
        if ($c === 'Miss')                                              { $mods[] = ['label' => 'Swing/Miss — heavy penalty', 'delta' => -6]; $m -= 6; }
        return $m;
    }

    /** 19. PRESSURE ROUND — Compete under pressure */
    private function pressureRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Barrel')                                            { $mods[] = ['label' => 'Clutch barrel',             'delta' => 5]; $m += 5; }
        if (in_array($c, ['Weak', 'Average'], true))                   { $mods[] = ['label' => 'Weak out',                  'delta' => -4]; $m -= 4; }
        return $m;
    }

    /**
     * 20. CHAMPIONSHIP ROUND — Weighted hitter evaluation
     *
     * Categories & weights:
     *   Contact Quality  30%
     *   Exit Velocity    20%
     *   Situational      20%
     *   Direction        15%
     *   Trajectory       15%
     */
    private function championshipRound(string $c, ?string $t, ?string $d, ?int $ev, array &$mods): int
    {
        // Contact Quality (30%) → 0–3 pts
        $contactPts = match ($c) {
            'Barrel'  => 3,
            'Hard'    => 2,
            'Average' => 1,
            default   => 0,
        };
        $mods[] = ['label' => "Contact quality ({$c})", 'delta' => $contactPts];

        // Exit Velocity (20%) → 0–2 pts
        $evPts = 0;
        if ($ev !== null) {
            $evPts = match (true) {
                $ev >= 100 => 2,
                $ev >= 92  => 1,
                default    => 0,
            };
            $mods[] = ['label' => "EV {$ev}mph", 'delta' => $evPts];
        }

        // Situational success (20%) → 0–2 pts
        $sitPts = ($c !== 'Miss' && $t !== null) ? 2 : 0;
        $mods[] = ['label' => 'Situational success', 'delta' => $sitPts];

        // Direction (15%) → 0–1.5 pts (rounded to int)
        $dirPts = match ($d) {
            'Oppo', 'Middle' => 1,
            default          => 0,
        };
        $mods[] = ['label' => "Direction ({$d})", 'delta' => $dirPts];

        // Trajectory (15%) → 0–1.5 pts (rounded to int)
        $trajPts = match ($t) {
            'LineDrive' => 1,
            'FlyBall'   => 1,
            default     => 0,
        };
        $mods[] = ['label' => "Trajectory ({$t})", 'delta' => $trajPts];

        // Override base entirely — return offset so base + delta = weighted sum
        $total = $contactPts + $evPts + $sitPts + $dirPts + $trajPts;
        return $total - (self::BASE_SCORES[$c] ?? 0);
    }
}
