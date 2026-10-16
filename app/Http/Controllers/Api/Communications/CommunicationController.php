<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Communications;

use App\Http\Controllers\Controller;
use App\Models\{CommunicationPreference,CommunicationDelivery,DailyPlan,User};
use App\Services\Communications\{CommunicationContent,CommunicationOutbox,InvitationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationController extends Controller
{
    public function preferences(Request $r)
    {
        return response()->json(['preferences' => CommunicationPreference::forUser($r->user()->id),'essential' => ['account_security','invitations','subscription_status'],'guardian_supported' => false])->header('Cache-Control', 'no-store');
    }
    public function savePreferences(Request $r)
    {
        $rules = array_fill_keys(array_keys(CommunicationPreference::DEFAULTS), 'sometimes|required|boolean');
        $data = $r->validate($rules);
        abort_if(array_diff(array_keys($r->all()), array_keys($rules)), 422, 'Unknown preference.');
        DB::transaction(function () use ($r, $data): void {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $p = CommunicationPreference::firstOrNew(['user_id' => $r->user()->id]);
            $p->fill($data)->save();
        });
        return $this->preferences($r);
    }
    public function inbox(Request $r, CommunicationContent $content)
    {
        $page = CommunicationDelivery::where('recipient_id', $r->user()->id)->latest()->paginate(25);
        $content->prime($page->getCollection());
        $page->setCollection($page->getCollection()->filter(fn ($d) => $content->allowed($d))->values()->map(fn ($d) => ['id' => $d->id,'event_type' => $d->event_type,'content' => $d->payload,'read_at' => $d->read_at,'created_at' => $d->created_at]));
        return response()->json($page)->header('Cache-Control', 'no-store');
    }
    public function read(Request $r, string $id, CommunicationContent $content)
    {
        $d = CommunicationDelivery::where('recipient_id', $r->user()->id)->findOrFail($id);
        abort_unless($content->allowed($d), 404);
        $d->update(['read_at' => $d->read_at ?? now()]);
        return response()->json(['read' => true]);
    }
    public function team(Request $r, string $teamId, InvitationService $access)
    {
        $access->authorize($r->user(), $teamId);
        return response()->json(['enabled' => (bool)config('communications.enabled'),'send_enabled' => (bool)config('communications.send_enabled'),
            'plans' => DailyPlan::with('assignments')->where('team_id', $teamId)->where('status', 'published')->orderByDesc('date')->limit(50)->get(['id','name','date','version']),
            'deliveries' => CommunicationDelivery::where('team_id', $teamId)->latest()->paginate(25, ['id','recipient_id','event_type','status','failure_code','created_at','sent_at'])])->header('Cache-Control', 'no-store');
    }
    public function announce(Request $r, string $teamId, InvitationService $access, CommunicationOutbox $out)
    {
        $access->authorize($r->user(), $teamId);
        abort_unless(config('communications.enabled'), 503, 'Communications are disabled.');
        $v = $r->validate(['message' => 'required|string|max:2000','request_id' => 'required|uuid']);
        $event = $out->record('TeamAnnouncement', 'team', $teamId, $v['request_id'], $teamId, null, ['message' => $v['message'],'coach_id' => $r->user()->id]);
        return response()->json(['event_id' => $event->id,'status' => 'recorded'], 202);
    }
    public function republish(Request $r, string $id, InvitationService $access, CommunicationOutbox $out)
    {
        abort_unless(config('communications.enabled'), 503, 'Communications are disabled.');
        $v = $r->validate(['version' => 'required|integer|min:1','confirmed' => 'required|accepted']);
        $event = DB::transaction(function () use ($r, $id, $access, $out, $v) {
            $plan = DailyPlan::whereKey($id)->lockForUpdate()->firstOrFail();
            $access->authorize($r->user(), $plan->team_id);
            abort_unless('published' === $plan->status, 409, 'Publish the plan first.');
            abort_unless($plan->version === $v['version'], 409, 'Reload the latest workout version.');
            return $out->record('WorkoutAssigned', 'daily_plan', $id, 'republish:'.$plan->version, $plan->team_id);
        });
        return response()->json(['event_id' => $event->id,'status' => 'recorded'], 202);
    }
}
