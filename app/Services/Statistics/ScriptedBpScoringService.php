<?php

declare(strict_types=1);

namespace App\Services\Statistics;

/**
 * FMTRX Scripted BP Scoring Engine
 *
 * Per-swing scale: 0–10 (clamped).
 * Base: Hard=6 | Average=4 | Weak=2 | Miss=0
 * Round score  = totalSwingPts / (swings * 10) * 100
 * BP score     = average(allRoundScores)
 * Grade: 90+ Elite | 80+ Winning | 70+ Competitive | 60+ Development | <60 Needs Work
 */
class ScriptedBpScoringService
{
    private const BASE_SCORES = [
        'Hard'    => 6,
        'Average' => 4,
        'Weak'    => 2,
        'Miss'    => 0,
    ];

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
            'BARREL'          => $this->barrelRound($contactType, $trajectory, $direction, $modifiers),
            'OPPO_GAP'        => $this->oppoGapRound($contactType, $trajectory, $direction, $modifiers),
            'PULL_DAMAGE'     => $this->pullDamageRound($contactType, $trajectory, $direction, $modifiers),
            'TWO_STRIKE'      => $this->twoStrikeRound($contactType, $trajectory, $direction, $modifiers),
            'SAC_FLY'         => $this->sacFlyRound($contactType, $trajectory, $modifiers),
            'HIT_AND_RUN'     => $this->hitAndRunRound($contactType, $trajectory, $direction, $modifiers),
            'LINE_DRIVE'      => $this->lineDriveRound($contactType, $trajectory, $direction, $modifiers),
            'HARD_CONTACT'    => $this->hardContactRound($contactType, $trajectory, $modifiers),
            'FASTBALL_HUNT'   => $this->fastballHuntRound($contactType, $trajectory, $direction, $modifiers),
            'OFFSPEED_ADJ'    => $this->offspeedAdjRound($contactType, $trajectory, $direction, $modifiers),
            'GAP_TO_GAP'      => $this->gapToGapRound($contactType, $trajectory, $direction, $modifiers),
            'INSIDE_PITCH'    => $this->insidePitchRound($contactType, $trajectory, $direction, $modifiers),
            'OUTSIDE_PITCH'   => $this->outsidePitchRound($contactType, $trajectory, $direction, $modifiers),
            'FIRST_PITCH_ATK' => $this->firstPitchAttackRound($contactType, $trajectory, $modifiers),
            'BEHIND_COUNT'    => $this->behindCountRound($contactType, $trajectory, $direction, $modifiers),
            'ADVANTAGE_COUNT' => $this->advantageCountRound($contactType, $trajectory, $direction, $modifiers),
            'OPPO_POWER'      => $this->oppoPowerRound($contactType, $trajectory, $direction, $modifiers),
            'CONTACT'         => $this->contactRound($contactType, $trajectory, $direction, $modifiers),
            'PRESSURE'        => $this->pressureRound($contactType, $trajectory, $direction, $modifiers),
            'CHAMPIONSHIP'    => $this->championshipRound($contactType, $trajectory, $direction, $modifiers),
            default           => 0,
        };

        return [
            'score'     => max(0, min(10, $base + $delta)),
            'modifiers' => $modifiers,
        ];
    }

    public function grade(float $bpScore): string
    {
        return match (true) {
            $bpScore >= 90 => 'Elite',
            $bpScore >= 80 => 'Winning',
            $bpScore >= 70 => 'Competitive',
            $bpScore >= 60 => 'Development',
            default        => 'Needs Work',
        };
    }

    private function barrelRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Middle')                                             { $mods[] = ['label' => 'Middle direction',      'delta' => 2];  $m += 2; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',            'delta' => 2];  $m += 2; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 2];  $m += 2; }
        return $m;
    }

    private function oppoGapRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Oppo')                                               { $mods[] = ['label' => 'Oppo direction',        'delta' => 3];  $m += 3; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball', 'delta' => 2];  $m += 2; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 2];  $m += 2; }
        if ($t === 'GroundBall' && $d === 'Pull')                        { $mods[] = ['label' => 'Pull-side rollover',    'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function pullDamageRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Pull')                                               { $mods[] = ['label' => 'Pull direction',        'delta' => 3];  $m += 3; }
        if (in_array($t, ['FlyBall', 'LineDrive'], true))                { $mods[] = ['label' => 'Fly ball / line drive', 'delta' => 2];  $m += 2; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 2];  $m += 2; }
        if ($c === 'Weak' && $t === 'GroundBall')                        { $mods[] = ['label' => 'Weak rollover',         'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function twoStrikeRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss')                                               { $mods[] = ['label' => 'Any contact',           'delta' => 3];  $m += 3; }
        if ($t === 'Foul')                                               { $mods[] = ['label' => 'Foul ball',             'delta' => 2];  $m += 2; }
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Middle / oppo contact', 'delta' => 2];  $m += 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',          'delta' => -4]; $m -= 4; }
        return $m;
    }

    private function sacFlyRound(string $c, ?string $t, array &$mods): int
    {
        $m = 0;
        if ($t === 'FlyBall')                                            { $mods[] = ['label' => 'Fly ball',              'delta' => 3];  $m += 3; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',            'delta' => 3];  $m += 3; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 2];  $m += 2; }
        if ($c === 'Weak' && $t === 'GroundBall')                        { $mods[] = ['label' => 'Weak ground ball',      'delta' => -3]; $m -= 3; }
        if ($t === 'PopUp')                                              { $mods[] = ['label' => 'Pop up',                'delta' => -2]; $m -= 2; }
        return $m;
    }

    private function hitAndRunRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'GroundBall')                                         { $mods[] = ['label' => 'Ground ball',           'delta' => 3];  $m += 3; }
        if (in_array($d, ['Middle', 'Oppo'], true))                      { $mods[] = ['label' => 'Middle / oppo direction','delta' => 3]; $m += 3; }
        if ($c !== 'Miss')                                               { $mods[] = ['label' => 'Contact made',          'delta' => 2];  $m += 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',          'delta' => -4]; $m -= 4; }
        if ($t === 'FlyBall' && $d === 'Pull')                           { $mods[] = ['label' => 'Pull-side fly ball',    'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function lineDriveRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',            'delta' => 4];  $m += 4; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 3];  $m += 3; }
        if (in_array($d, ['Middle', 'Oppo'], true))                      { $mods[] = ['label' => 'Middle / oppo',        'delta' => 1];  $m += 1; }
        if ($t === 'PopUp')                                              { $mods[] = ['label' => 'Pop up',                'delta' => -4]; $m -= 4; }
        return $m;
    }

    private function hardContactRound(string $c, ?string $t, array &$mods): int
    {
        $m = 0;
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',          'delta' => 4];  $m += 4; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',            'delta' => 2];  $m += 2; }
        if ($t === 'FlyBall')                                            { $mods[] = ['label' => 'Fly ball',              'delta' => 1];  $m += 1; }
        if ($c === 'Weak')                                               { $mods[] = ['label' => 'Weak contact',          'delta' => -2]; $m -= 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',          'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function fastballHuntRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 3];  $m += 3; }
        if (in_array($d, ['Pull', 'Middle'], true))                      { $mods[] = ['label' => 'Pull / middle direction','delta' => 2];  $m += 2; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if (in_array($c, ['Weak', 'Average'], true) && $t === 'GroundBall') { $mods[] = ['label' => 'Late swing',         'delta' => -2]; $m -= 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Take strike',            'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function offspeedAdjRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 3];  $m += 3; }
        if (in_array($d, ['Oppo', 'Middle'], true))                      { $mods[] = ['label' => 'Oppo / middle direction','delta' => 3];  $m += 3; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',             'delta' => 2];  $m += 2; }
        if ($t === 'GroundBall' && $d === 'Pull')                        { $mods[] = ['label' => 'Out-front rollover',     'delta' => -3]; $m -= 3; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',           'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function gapToGapRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Middle / oppo direction','delta' => 2];  $m += 2; }
        if (in_array($d, ['Pull', 'Oppo'], true) && $c !== 'Miss')      { $mods[] = ['label' => 'Pull gap / oppo gap',    'delta' => 3];  $m += 3; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if ($d === 'Pull' && $c === 'Weak')                              { $mods[] = ['label' => 'Dead pull rollover',      'delta' => -2]; $m -= 2; }
        return $m;
    }

    private function insidePitchRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Pull')                                               { $mods[] = ['label' => 'Pull-side contact',      'delta' => 3];  $m += 3; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 3];  $m += 3; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if ($c === 'Weak')                                               { $mods[] = ['label' => 'Jammed weak contact',    'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function outsidePitchRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Oppo')                                               { $mods[] = ['label' => 'Oppo direction',         'delta' => 4];  $m += 4; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 2];  $m += 2; }
        if ($t === 'GroundBall' && $d === 'Pull')                        { $mods[] = ['label' => 'Pull-side rollover',     'delta' => -4]; $m -= 4; }
        return $m;
    }

    private function firstPitchAttackRound(string $c, ?string $t, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss')                                               { $mods[] = ['label' => 'Swing at strike',        'delta' => 2];  $m += 2; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 4];  $m += 4; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Passive take',           'delta' => -4]; $m -= 4; }
        if ($c === 'Weak')                                               { $mods[] = ['label' => 'Weak contact',           'delta' => -2]; $m -= 2; }
        return $m;
    }

    private function behindCountRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss')                                               { $mods[] = ['label' => 'Any contact',            'delta' => 3];  $m += 3; }
        if ($t === 'Foul')                                               { $mods[] = ['label' => 'Foul ball',              'delta' => 2];  $m += 2; }
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Middle / oppo',          'delta' => 2];  $m += 2; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',             'delta' => 2];  $m += 2; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',           'delta' => -5]; $m -= 5; }
        return $m;
    }

    private function advantageCountRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 4];  $m += 4; }
        if (in_array($t, ['LineDrive', 'FlyBall'], true))                { $mods[] = ['label' => 'Line drive / fly ball',  'delta' => 2];  $m += 2; }
        if (in_array($d, ['Pull', 'Middle'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Pull / middle damage',   'delta' => 2];  $m += 2; }
        if ($c === 'Weak')                                               { $mods[] = ['label' => 'Weak contact',           'delta' => -4]; $m -= 4; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Take strike',            'delta' => -3]; $m -= 3; }
        return $m;
    }

    private function oppoPowerRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($d === 'Oppo')                                               { $mods[] = ['label' => 'Oppo direction',         'delta' => 3];  $m += 3; }
        if (in_array($t, ['FlyBall', 'LineDrive'], true))                { $mods[] = ['label' => 'Fly ball / line drive',  'delta' => 3];  $m += 3; }
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 3];  $m += 3; }
        if ($c === 'Weak' && $d === 'Oppo')                              { $mods[] = ['label' => 'Weak oppo contact',      'delta' => -2]; $m -= 2; }
        return $m;
    }

    private function contactRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c !== 'Miss')                                               { $mods[] = ['label' => 'Any contact',            'delta' => 4];  $m += 4; }
        if (in_array($d, ['Middle', 'Oppo'], true) && $c !== 'Miss')    { $mods[] = ['label' => 'Middle / oppo',          'delta' => 2];  $m += 2; }
        if (in_array($t, ['LineDrive', 'GroundBall'], true))             { $mods[] = ['label' => 'Line drive / ground ball','delta' => 1];  $m += 1; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',           'delta' => -5]; $m -= 5; }
        return $m;
    }

    private function pressureRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $m = 0;
        if ($c === 'Hard')                                               { $mods[] = ['label' => 'Hard contact',           'delta' => 3];  $m += 3; }
        if ($t === 'LineDrive')                                          { $mods[] = ['label' => 'Line drive',             'delta' => 2];  $m += 2; }
        if ($c !== 'Miss' && in_array($d, ['Middle', 'Oppo'], true))    { $mods[] = ['label' => 'Executes round goal',    'delta' => 3];  $m += 3; }
        if ($c === 'Weak')                                               { $mods[] = ['label' => 'Weak out',               'delta' => -3]; $m -= 3; }
        if ($c === 'Miss')                                               { $mods[] = ['label' => 'Swing / Miss',           'delta' => -4]; $m -= 4; }
        return $m;
    }

    private function championshipRound(string $c, ?string $t, ?string $d, array &$mods): int
    {
        $contact = match ($c) { 'Hard' => 4, 'Average' => 2, 'Weak' => 1, default => 0 };
        $mods[] = ['label' => 'Contact quality', 'delta' => $contact];

        $dir = in_array($d, ['Middle', 'Oppo'], true) ? 2 : ($d === 'Pull' ? 1 : 0);
        $mods[] = ['label' => 'Direction success', 'delta' => $dir];

        $traj = in_array($t, ['LineDrive', 'FlyBall'], true) ? 2 : ($t === 'GroundBall' ? 1 : 0);
        $mods[] = ['label' => 'Trajectory success', 'delta' => $traj];

        $sit = ($c !== 'Miss' && $t !== null) ? 2 : 0;
        $mods[] = ['label' => 'Situation execution', 'delta' => $sit];

        return ($contact + $dir + $traj + $sit) - (self::BASE_SCORES[$c] ?? 0);
    }
}
