<?php
namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Models\{CoachTeam, PlayerTeam, PlayerVideo, PlayerVideoShare, Team, User};
use App\Services\Video\VideoAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlayerVideoController extends Controller
{
    public function __construct(private VideoAccess $access) {}

    public function context(Request $request)
    {
        $actor = $request->user();
        abort_unless(in_array($actor->type, ['player', 'coach']), 403);
        $ids = $actor->type === 'player' ? $this->access->playerTeams($actor->id) : $this->access->coachTeams($actor->id);
        $teams = Team::whereIn('id', $ids)->get(['id', 'name']);
        $players = $actor->type === 'player' ? collect([$actor->load('profile')]) : User::where('type', 'player')
            ->whereIn('id', PlayerTeam::whereIn('team_id', $ids)->where('actual', true)->select('user_id'))->with('profile')->get();
        $coaches = CoachTeam::whereIn('team_id', $ids)->with('profile')->get();
        return response()->json(['role' => $actor->type, 'self_id' => $actor->id, 'teams' => $teams,
            'players' => $players->map(fn ($p) => ['id' => $p->id, 'name' => trim(($p->profile?->first_name ?? '').' '.($p->profile?->last_name ?? '')) ?: 'Player',
                'team_ids' => $this->access->playerTeams($p->id)->intersect($ids)->values()]),
            'coaches' => $coaches->map(fn ($c) => ['id' => $c->coach_id, 'team_id' => $c->team_id,
                'name' => trim(($c->profile?->first_name ?? '').' '.($c->profile?->last_name ?? '')) ?: 'Coach']),
            'max_upload_bytes' => 100 * 1024 * 1024]);
    }

    private function item(PlayerVideo $video, User $viewer): array
    {
        $own = $viewer->id === $video->player_id;
        return ['id' => $video->id, 'player_id' => $video->player_id, 'title' => $video->title,
            'category' => $video->category, 'notes' => $video->notes, 'bytes' => $video->bytes,
            'created_at' => $video->created_at, 'uploaded_by_you' => $viewer->id === $video->uploaded_by,
            'can_manage_sharing' => $own, 'can_delete' => $own || $viewer->id === $video->uploaded_by,
            'shares' => $own ? $video->shares()->with(['team', 'coach.profile'])->get()->map(fn ($s) => [
                'id' => $s->id, 'team_id' => $s->team_id, 'coach_id' => $s->coach_id,
                'name' => $s->coach_id ? (trim(($s->coach?->profile?->first_name ?? '').' '.($s->coach?->profile?->last_name ?? '')) ?: 'Coach') : ($s->team?->name ?? 'Former team'),
            ]) : []];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['player_id' => 'required|uuid']);
        $page = $this->access->videos($request->user(), $data['player_id'])->latest()->orderBy('id')->paginate(20);
        return response()->json(['data' => $page->getCollection()->map(fn ($v) => $this->item($v, $request->user())), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['player_id' => 'required|uuid', 'team_id' => 'nullable|uuid',
            'title' => 'required|string|max:120', 'category' => 'required|in:pitching,hitting,fielding,other',
            'notes' => 'nullable|string|max:2000', 'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/webm|max:102400']);
        $actor = $request->user();
        $own = $actor->type === 'player' && $actor->id === $data['player_id'];
        abort_unless($own || $this->access->roster($actor, $data['player_id'], $data['team_id'] ?? ''), 403);
        $path = null;
        try {
            $video = DB::transaction(function () use ($request, $actor, $data, $own, &$path) {
                User::whereKey($data['player_id'])->lockForUpdate()->firstOrFail();
                $existing = PlayerVideo::where('player_id', $data['player_id']);
                if ((clone $existing)->count() >= 50 || (clone $existing)->sum('bytes') + $request->file('video')->getSize() > 2 * 1024 * 1024 * 1024) {
                    throw ValidationException::withMessages(['video' => 'This library is full (50 videos or 2 GB). Delete a clip before uploading.']);
                }
                $file = $request->file('video');
                $path = $file->storeAs('clips', Str::uuid().'.'.$file->extension(), 'player_videos');
                if (!$path) throw new \RuntimeException('Video storage failed.');
                $video = PlayerVideo::create(['player_id' => $data['player_id'], 'uploaded_by' => $actor->id,
                    'title' => $data['title'], 'category' => $data['category'], 'notes' => $data['notes'] ?? null,
                    'path' => $path, 'mime' => $file->getMimeType(), 'bytes' => $file->getSize()]);
                if (!$own) $video->shares()->create(['team_id' => $data['team_id'], 'coach_id' => $actor->id]);
                return $video;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('player_videos')->delete($path);
            throw $e;
        }
        return response()->json(['data' => $this->item($video, $actor)], 201);
    }

    public function content(Request $request, PlayerVideo $video)
    {
        $this->access->view($request->user(), $video);
        $disk = Storage::disk('player_videos');
        abort_unless($disk->exists($video->path), 404);
        return response()->file($disk->path($video->path), ['Content-Type' => $video->mime,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function share(Request $request, PlayerVideo $video)
    {
        abort_unless($request->user()->type === 'player' && $request->user()->id === $video->player_id, 403);
        $data = $request->validate(['team_id' => 'required|uuid', 'coach_id' => 'nullable|uuid']);
        abort_unless($this->access->playerTeams($video->player_id)->contains($data['team_id']), 403);
        if (!empty($data['coach_id'])) abort_unless(CoachTeam::where('team_id', $data['team_id'])->where('coach_id', $data['coach_id'])->whereHas('team')->exists(), 403);
        DB::transaction(function () use ($video, $data) {
            PlayerVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
            $video->shares()->firstOrCreate(['team_id' => $data['team_id'], 'coach_id' => $data['coach_id'] ?? null]);
        });
        return response()->json(['data' => $this->item($video, $request->user())]);
    }

    public function revoke(Request $request, PlayerVideo $video, string $share)
    {
        abort_unless($request->user()->type === 'player' && $request->user()->id === $video->player_id, 403);
        $video->shares()->whereKey($share)->firstOrFail()->delete();
        return response()->noContent();
    }

    public function destroy(Request $request, PlayerVideo $video)
    {
        $this->access->view($request->user(), $video);
        abort_unless(in_array($request->user()->id, [$video->player_id, $video->uploaded_by]), 403);
        // Keep the record available for retry if the filesystem cannot delete it.
        abort_unless(Storage::disk('player_videos')->delete($video->path), 503, 'Video could not be deleted. Please retry.');
        $video->delete();
        return response()->noContent();
    }
}
