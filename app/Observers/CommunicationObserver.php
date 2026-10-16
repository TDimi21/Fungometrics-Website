<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\{User,DailyPlan,DailyPlanAssignment,FreeAssessment,PlayerAssessment,WorkoutProgram,SubscriptionAudit};
use App\Services\Communications\CommunicationOutbox;
use Illuminate\Database\Eloquent\Model;

class CommunicationObserver
{
    public function saved(Model $m): void
    {
        if ( ! config('communications.enabled')) {
            return;
        }
        $out = app(CommunicationOutbox::class);
        if ($m instanceof User && 'coach' === $m->type && filled($m->password) && filled($m->email) && ($m->wasRecentlyCreated || ($m->wasChanged('password') && blank($m->getOriginal('password'))))) {
            $out->record('CoachRegistered', 'user', $m->id, 'initial', null, $m->id);
        } elseif ($m instanceof DailyPlan && 'published' === $m->status && ($m->wasRecentlyCreated || $m->wasChanged('status'))) {
            $out->record('WorkoutAssigned', 'daily_plan', $m->id, 'initial', $m->team_id);
        } elseif ($m instanceof DailyPlanAssignment && $m->wasRecentlyCreated) {
            $plan = $m->plan;
            if ('published' === $plan?->status) {
                $out->record('WorkoutAssigned', 'daily_plan', $plan->id, 'initial', $plan->team_id, $m->user_id);
            }
        } elseif ($m instanceof FreeAssessment && 'completed' === $m->status && $m->report_snapshot && $m->wasChanged('status')) {
            $out->record('AssessmentCompleted', 'free_assessment', $m->id, 'initial', $m->team_id);
        } elseif ($m instanceof PlayerAssessment && $m->wasRecentlyCreated) {
            // Drafts are stored separately in AssessmentDraft; this is a saved assessment.
            $out->record('AssessmentCompleted', 'player_assessment', $m->id, 'initial', $m->team_id, $m->user_id);
        } elseif ($m instanceof WorkoutProgram && 'published' === $m->status && ($m->wasChanged('schedule') || $m->wasChanged('status'))) {
            $out->record('DevelopmentPlanPublished', 'workout_program', $m->id, 'initial', $m->team_id, null, ['version' => $m->version]);
            // Later publication batches can introduce new recipients. Delivery dedupe still uses initial per recipient.
            if ($m->version > 1) {
                $out->record('DevelopmentPlanPublished', 'workout_program', $m->id, 'batch:'.$m->version, $m->team_id);
            }
        }
    }
    public function created(Model $m): void
    {
        if ( ! config('communications.enabled') || ! $m instanceof SubscriptionAudit || 'subscription.provider_reconciled' !== $m->action) {
            return;
        }
        $after = $m->after_state ?? [];
        $before = $m->before_state ?? [];
        if (($after['provider'] ?? null) !== 'revenuecat' || empty($after['user_id'])) {
            return;
        }
        $fields = ['status','plan_id','current_period_ends_at','grace_period_ends_at'];
        $changed = collect($fields)->contains(fn ($k) => ($before[$k] ?? null) !== ($after[$k] ?? null));
        $cancelChanged = (bool)($before['canceled_at'] ?? null) !== (bool)($after['canceled_at'] ?? null);
        if ( ! $changed && ! $cancelChanged) {
            return;
        }
        $providerEventId = data_get($after, 'metadata.revenuecat.id');
        $occurrence = 'SYNC' === $m->reason ? 'sync:'.$m->id : 'provider:'.($providerEventId ?: $m->id);
        app(CommunicationOutbox::class)->record('SubscriptionStatusChanged', 'subscription', (string)$m->subscription_id, $occurrence, null, $after['user_id'], [
            'status' => $after['status'] ?? null,'previous_status' => $before['status'] ?? null,'reason' => $m->reason,'canceled' => ! empty($after['canceled_at']),'period_end' => $after['current_period_ends_at'] ?? null,
        ]);
    }
}
