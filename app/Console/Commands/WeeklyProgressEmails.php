<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{CommunicationPreference,PlayerTeam,CoachTeam,User};
use App\Services\Communications\CommunicationOutbox;
use Illuminate\Console\Command;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

class WeeklyProgressEmails extends Command
{
    protected $signature = 'fmtrx:weekly-progress {--week= : Completed week starting Monday (YYYY-MM-DD)} {--dry-run : Count eligible memberships without recording events}';
    protected $description = 'Record opt-in weekly progress events without sending inline.';
    public function handle(CommunicationOutbox $out): int
    {
        if( ! config('communications.enabled') || ! config('communications.weekly_enabled')) {
            $this->info('Weekly communications are disabled.');
            return self::SUCCESS;
        }
        $raw = $this->option('week') ?: now()->startOfWeek(Carbon::MONDAY)->subWeek()->toDateString();
        try {
            $week = Carbon::createFromFormat('!Y-m-d', $raw);
            if($week->format('Y-m-d') !== $raw || ! $week->isMonday() || $week->copy()->addWeek()->isFuture()) {
                throw new InvalidArgumentException();
            }
        } catch(Throwable) {
            $this->error('Choose a completed week starting Monday, YYYY-MM-DD.');
            return self::FAILURE;
        }
        $ids = CommunicationPreference::where('weekly_reports', true)->select('user_id');
        $members = PlayerTeam::where('actual', true)->whereIn('user_id', $ids)->whereHas('team')->whereHas('user', fn ($q) => $q->where('type', 'player')->where('is_dummy', false)->whereNotNull('email')->whereNotNull('password'));
        $count = 0;
        foreach($members->lazyById(100) as $m) {
            $count++;
            if( ! $this->option('dry-run')) {
                $out->record('WeeklyProgressReady', 'team', $m->team_id, $week->toDateString(), $m->team_id, $m->user_id, ['week_start' => $week->toDateString()]);
            }
        }
        $coachIds = User::whereIn('id', CommunicationPreference::where('weekly_reports', true)->select('user_id'))->where('type', 'coach')->where('is_dummy', false)->whereNotNull('email')->whereNotNull('password')->select('id');
        foreach(CoachTeam::whereIn('coach_id', $coachIds)->whereHas('team')->lazyById(100) as $m) {
            $count++;
            if( ! $this->option('dry-run')) {
                $out->record('WeeklyProgressReady', 'team', $m->team_id, $week->toDateString(), $m->team_id, $m->coach_id, ['week_start' => $week->toDateString()]);
            }
        }
        $this->info($count.' opted-in team memberships considered. Empty reports are skipped by workers.');
        return self::SUCCESS;
    }
}
