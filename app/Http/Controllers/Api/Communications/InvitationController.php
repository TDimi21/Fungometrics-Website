<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Communications;

use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Http\Controllers\Controller;
use App\Models\{TeamInvitation, User};
use App\Services\Communications\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    public function index(Request $request, string $teamId, InvitationService $service)
    {
        $service->authorize($request->user(), $teamId);
        $request->validate(['focus_player' => 'sometimes|uuid']);
        $players = User::with('profile')->where('type', 'player')
            ->whereHas('team_players', fn ($q) => $q->where('team_id', $teamId)->where('actual', true))->orderBy('id')->paginate(50, ['*'], 'players_page');
        $focused = $request->filled('focus_player') ? User::with('profile')->where('type', 'player')->whereHas('team_players', fn ($q) => $q->where('team_id', $teamId)->where('actual', true))->find($request->input('focus_player')) : null;
        return response()->json([
            'focused_player' => $focused ? ['id' => $focused->id, 'name' => trim(($focused->profile?->first_name ?? '').' '.($focused->profile?->last_name ?? '')), 'email' => $focused->email, 'connected' => filled($focused->password)] : null,
            'enabled' => (bool)config('communications.invitations_enabled'),
            'delivery_enabled' => (bool)(config('communications.enabled') && config('communications.send_enabled') && config('communications.categories.invitations')),
            'pilot_mode' => (bool)config('communications.pilot_mode', true),
            'can_invite_coaches' => CoachUtils::isHeadCoach($request->user()->id, $teamId),
            'players' => $players->through(fn ($u) => ['id' => $u->id,'name' => trim(($u->profile?->first_name ?? '').' '.($u->profile?->last_name ?? '')),'email' => $u->email,'connected' => filled($u->password)]),
            'invitations' => TeamInvitation::where('team_id', $teamId)->orderByDesc('created_at')->paginate(50)->through(fn ($i) => $this->present($i)),
        ])->header('Cache-Control', 'no-store');
    }
    public function store(Request $request, string $teamId, InvitationService $service)
    {
        $data = $request->validate(['role' => 'required|in:player,coach','email' => 'required|email:rfc|max:191','user_id' => 'required_if:role,player|nullable|uuid']);
        return response()->json($this->present($service->send($request->user(), $teamId, $data)), 202);
    }
    public function resend(Request $request, string $id, InvitationService $service)
    {
        return response()->json($this->present($service->manage($request->user(), $id, true)), 202);
    }
    public function revoke(Request $request, string $id, InvitationService $service)
    {
        return response()->json($this->present($service->manage($request->user(), $id, false)));
    }
    public function inspect(Request $request, InvitationService $service)
    {
        $data = $request->validate(['token' => 'required|string|size:64']);
        $invite = $service->resolve($data['token']);
        // No player, team, email, account-existence or historical data on this public endpoint.
        return response()->json(['valid' => true,'expires_at' => $invite->expires_at->toIso8601String()])->header('Cache-Control', 'no-store');
    }
    public function playerContext(Request $request, InvitationService $service)
    {
        $data = $request->validate(['token' => 'required|string|size:64']);
        $invite = $service->resolve($data['token']);
        abort_unless($invite->role === 'player', 422, 'Use the coach invitation screen for this invitation.');
        app(\App\Services\Communications\InvitationEligibility::class)->assertAvailable($invite);
        $actor = User::findOrFail($invite->invited_by);
        $service->authorize($actor, $invite->team_id);
        $player = User::findOrFail($invite->user_id);
        $team = \App\Models\Team::findOrFail($invite->team_id);
        // Only the bearer of a valid invitation gets the minimum form context.
        // No player history, account IDs or general email lookup is exposed.
        return response()->json([
            'email' => $invite->email,
            'team_name' => $team->name,
            'team_code' => $team->join_code,
            'has_password' => filled($player->password),
            'signed_in' => Auth::guard('sanctum')->check(),
            'matching_account' => Auth::guard('sanctum')->id() === $player->id,
            'expires_at' => $invite->expires_at->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function accept(Request $request, InvitationService $service)
    {
        $data = $request->validate(['token' => 'required_without:claim_code|prohibits:claim_code|nullable|string|size:64','claim_code' => 'required_without:token|prohibits:token|nullable|string|max:24|regex:/^[a-fA-F0-9 -]+$/','email' => 'required|email:rfc|max:191','password' => 'nullable|string|min:8|max:20','first_name' => 'nullable|string|max:100','last_name' => 'nullable|string|max:100']);
        $role = $service->accept($data, Auth::guard('sanctum')->user());
        return response()->json(['accepted' => true,'login_path' => '/login/'.$role,'dashboard_path' => 'player' === $role ? '/player-dashboard' : '/dashboard'])->header('Cache-Control', 'no-store');
    }
    private function present(TeamInvitation $i): array
    {
        return ['id' => $i->id,'user_id' => $i->user_id,'email' => $i->email,'role' => $i->role,'status' => $i->state(),'permission' => 'coach' === $i->role ? 'assistant_coach' : 'player','delivery_status' => $i->delivery_status,'failure_code' => $i->failure_code,'expires_at' => $i->expires_at,'sent_at' => $i->sent_at,'accepted_at' => $i->accepted_at];
    }
}
