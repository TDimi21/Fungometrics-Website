<?php

declare(strict_types=1);

namespace App\Services\Communications;

use InvalidArgumentException;
use App\Models\Practice;
use App\Models\{CommunicationEvent,CommunicationDelivery,CommunicationPreference,DailyPlan,DailyPlanAssignment,FreeAssessment,PlayerAssessment,WorkoutProgram,PlayerTeam,CoachTeam,User,Team,Subscription};

class CommunicationContent
{
    private array $resources = [];
    private array $coachNames = [];
    private array $teamAccess = [];
    private array $primedEvents = [];
    private array $primedRecipients = [];
    private function coachName(?string $id): string
    {
        if ( ! $id) {
            return 'Your coach';
        }
        if ( ! isset($this->coachNames[$id])) {
            $u = User::with('profile')->find($id);
            $this->coachNames[$id] = trim(($u?->profile?->first_name ?? '').' '.($u?->profile?->last_name ?? '')) ?: 'Your coach';
        }
        return $this->coachNames[$id];
    }
    public function prime($deliveries): void
    {
        $events = CommunicationEvent::whereIn('id', $deliveries->pluck('event_id'))->get();
        $this->primedEvents = $events->keyBy('id')->all();
        $models = ['practice' => Practice::class,'daily_plan' => DailyPlan::class,'free_assessment' => FreeAssessment::class,'player_assessment' => PlayerAssessment::class,'workout_program' => WorkoutProgram::class,'user' => User::class,'subscription' => Subscription::class,'team' => Team::class];
        foreach($events->groupBy('resource_type') as $type => $rows) {
            if( ! isset($models[$type])) {
                continue;
            }
            $query = $models[$type]::whereIn('id', $rows->pluck('resource_id'));
            if('daily_plan' === $type) {
                $query->with('assignments');
            }
            if('free_assessment' === $type) {
                $query->with(['results','participants']);
            }
            foreach($rows as $e) {
                $this->resources[$type.':'.$e->resource_id] = null;
            }
            foreach($query->get() as $r) {
                $this->resources[$type.':'.$r->id] = $r;
            }
        }
        $programIds = [];
        foreach($this->resources as $r) {
            if($r instanceof WorkoutProgram) {
                $programIds = array_merge($programIds, $this->programPlans($r));
            }
        }
        $assignments = DailyPlanAssignment::whereIn('plan_id', $programIds)->where('schedule_status', 'active')->whereHas('plan', fn ($q) => $q->where('status', 'published'))->get(['plan_id','user_id']);
        foreach($this->resources as $r) {
            if($r instanceof WorkoutProgram) {
                $this->primedRecipients[$r->id] = $assignments->whereIn('plan_id', $this->programPlans($r))->pluck('user_id')->unique()->all();
            }
        }
    }
    public const CATEGORIES = ['LiveABCompleted' => 'team_announcements','WorkoutAssigned' => 'workouts','AssessmentCompleted' => 'assessments','DevelopmentPlanPublished' => 'development_plans','WeeklyProgressReady' => 'weekly_reports','TeamAnnouncement' => 'team_announcements'];
    private function resource(CommunicationEvent $e)
    {
        $key = $e->resource_type.':'.$e->resource_id;
        if ( ! array_key_exists($key, $this->resources)) {
            $this->resources[$key] = match($e->resource_type) {
                'practice' => Practice::find($e->resource_id),
                'daily_plan' => DailyPlan::with('assignments')->find($e->resource_id),
                'free_assessment' => FreeAssessment::with(['results','participants'])->find($e->resource_id),
                'player_assessment' => PlayerAssessment::find($e->resource_id),
                'workout_program' => WorkoutProgram::find($e->resource_id),
                'user' => User::find($e->resource_id),
                'subscription' => Subscription::find($e->resource_id),
                'team' => Team::find($e->resource_id),default => null
            };
        }
        return $this->resources[$key];
    }
    public function programPlans(WorkoutProgram $p): array
    {
        return collect($p->schedule ?? [])->filter(fn ($row) => ! empty($row['published_at']))
            ->flatMap(fn ($row) => array_values($row['daily_plan_ids'] ?? (isset($row['daily_plan_id']) ? [$row['daily_plan_id']] : [])))->unique()->values()->all();
    }
    public function recipientIds(CommunicationEvent $e): array
    {
        $r = $this->resource($e);
        if ( ! $r) {
            return [];
        }
        $ids = match($e->resource_type) {
            'practice' => $r->type === 'L' && $r->is_completed ? LiveABCompletion::participantIds($r) : [],
            'daily_plan' => 'published' === $r->status ? $r->assignments->filter(fn ($a) => ($a->schedule_status ?? 'active') === 'active')->pluck('user_id')->all() : [],
            'free_assessment' => 'completed' === $r->status && $r->report_snapshot ? $r->results->pluck('player_id')->intersect($r->participants->pluck('id'))->unique()->all() : [],
            'player_assessment' => [$r->user_id],
            'workout_program' => 'published' === $r->status ? ($this->primedRecipients[$r->id] ?? DailyPlanAssignment::whereIn('plan_id', $this->programPlans($r))->where('schedule_status', 'active')->whereHas('plan', fn ($q) => $q->where('status', 'published'))->pluck('user_id')->unique()->all()) : [],
            'subscription' => $r->user_id ? [$r->user_id] : [],
            'user' => [$r->id],
            'team' => $e->recipient_id ? [$e->recipient_id] : array_merge(PlayerTeam::where('team_id', $r->id)->where('actual', true)->pluck('user_id')->all(), CoachTeam::where('team_id', $r->id)->pluck('coach_id')->all()),default => []
        };
        return $e->recipient_id ? array_values(array_intersect($ids, [$e->recipient_id])) : $ids;
    }
    public function recipients(CommunicationEvent $e): iterable
    {
        $query = User::with('profile')->whereIn('id', $this->recipientIds($e))->where('is_dummy', false)->whereNotNull('password');
        if ($e->resource_type === 'practice') $query->where('type', 'player');
        if ($e->team_id) {
            $query->where(fn ($q) => $q->whereHas('team_players', fn ($m) => $m->where('team_id', $e->team_id)->where('actual', true))->orWhereHas('teamsCoach', fn ($m) => $m->where('team_id', $e->team_id)));
        }
        return $query->lazyById(100);
    }
    public function allowed(CommunicationDelivery $d): bool
    {
        $e = $this->primedEvents[$d->event_id] ?? CommunicationEvent::find($d->event_id);
        if ( ! $e || ! in_array($d->recipient_id, $this->recipientIds($e), true)) {
            return false;
        }
        if ($d->team_id) {
            if( ! isset($this->teamAccess[$d->recipient_id])) {
                $this->teamAccess[$d->recipient_id] = array_merge(PlayerTeam::where('user_id', $d->recipient_id)->where('actual', true)->whereHas('team')->pluck('team_id')->all(), CoachTeam::where('coach_id', $d->recipient_id)->whereHas('team')->pluck('team_id')->all());
            }
            if( ! in_array($d->team_id, $this->teamAccess[$d->recipient_id], true)) {
                return false;
            }
        }
        return true;
    }
    public function url(string $path): string
    {
        $origin = rtrim((string)config('communications.frontend_url'), '/');
        if ('https://app.fmtrx.com' !== $origin || ! preg_match('~^/(dashboard|player-dashboard|assessment-reports|development/player|settings|session/report/[a-fA-F0-9-]{36}/live_ab)(\?|$)~', $path)) {
            throw new InvalidArgumentException('Invalid frontend route.');
        }
        return $origin.$path;
    }
    public function build(CommunicationEvent $e, User $u): ?array
    {
        $r = $this->resource($e);
        if ( ! $r) {
            return null;
        }
        $lines = [];
        $path = '/player-dashboard';
        $heading = 'FMTRX update';
        switch($e->event_type) {
            case 'LiveABCompleted':
                $heading = 'Your Live AB results are ready';
                $lines = ['Your coach finalized this game. Sign in to view your individual results.'];
                $path = '/session/report/'.rawurlencode($r->id).'/live_ab?player='.rawurlencode($u->id);
                break;
            case 'CoachRegistered': $heading = 'Welcome to FMTRX';
                $lines = ['Your coach account is ready. Build your roster, plan training, and follow player development.'];
                $path = '/dashboard';
                break;
            case 'WorkoutAssigned':
                $heading = 'Your workout is ready';
                $assignment = $r->assignments->firstWhere('user_id', $u->id);
                $date = ($assignment?->scheduled_date ?? $r->date)?->format('Y-m-d');
                $name = $this->coachName($r->created_by);
                $lines = [$r->name,'Scheduled: '.$date,'Coach: '.($name ?: 'Your coach')];
                $path = '/player-dashboard?tab=workout&date='.rawurlencode($date ?? '').'&plan='.rawurlencode($r->id);
                break;
            case 'AssessmentCompleted':
                $heading = 'Your assessment results are ready';
                $lines = ['free_assessment' === $e->resource_type ? $r->name : ucfirst($r->type ?? 'Player').' assessment','Completed: '.$r->assessment_date?->format('Y-m-d'),'Sign in to view your private report.'];
                $path = '/assessment-reports?player='.rawurlencode($u->id).('free_assessment' === $e->resource_type ? '&assessment='.rawurlencode($r->id) : '');
                break;
            case 'DevelopmentPlanPublished':
                $heading = 'Your development plan is ready';
                $lines = [$r->name,'Start date: '.$r->start_date,'Coach: '.$this->coachName($r->created_by),'Your published workouts are available on your dashboard.'];
                $path = '/player-dashboard?tab=workout';
                break;
            case 'WeeklyProgressReady':
                if ( ! CommunicationPreference::forUser($u->id)['weekly_reports']) {
                    return null;
                }
                $lines = 'coach' === $u->type ? app(WeeklyProgressContent::class)->buildForCoach($e->team_id, $e->payload['week_start']) : app(WeeklyProgressContent::class)->build($u->id, $e->team_id, $e->payload['week_start']);
                if ( ! $lines) {
                    return null;
                }
                $heading = 'Your weekly development progress';
                $path = 'coach' === $u->type ? '/dashboard' : '/development/player';
                break;
            case 'TeamAnnouncement':$heading = 'Team announcement';
                $lines = [$e->payload['message'] ?? ''];
                $path = 'coach' === $u->type ? '/dashboard' : '/player-dashboard';
                break;
            case 'SubscriptionStatusChanged':
                $p = $e->payload;
                $status = $p['status'] ?? '';
                $heading = 'FMTRX subscription update';
                if (($p['canceled'] ?? false) && 'active' === $status) {
                    $lines = ['Automatic renewal has been canceled. Your subscription remains active'.( ! empty($p['period_end']) ? ' until '.$p['period_end'] : ' for its confirmed period').'.'];
                } elseif ('expired' === $status) {
                    $lines = ['Your subscription has expired.'];
                } elseif (in_array($status, ['past_due','grace_period'], true)) {
                    $lines = ['A billing problem was confirmed by your subscription provider.','Current status: '.str_replace('_', ' ', $status).'.'];
                } elseif ('revoked' === $status) {
                    $lines = ['Your subscription access was revoked by the provider.'];
                } elseif (in_array($status, ['active','trialing'], true)) {
                    $lines = [match($p['reason'] ?? '') {
                        'RENEWAL' => 'Your subscription renewed.','UNCANCELLATION','REFUND_REVERSED' => 'Your subscription was restored.',default => in_array($p['previous_status'] ?? null, ['expired','revoked','past_due'], true) ? 'Your subscription was restored.' : 'Your subscription is active.'
                    }];
                } else {
                    return null;
                }
                $path = '/settings';
                break;
            default:return null;
        }
        return ['heading' => $heading,'lines' => $lines,'action_text' => 'Open FMTRX','action_url' => $this->url($path),'preferences_url' => $this->url('/settings')];
    }
}
