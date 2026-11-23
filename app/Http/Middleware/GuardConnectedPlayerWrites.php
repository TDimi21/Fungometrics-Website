<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};

/** Stale/offline clients must refresh, never write back to an archived identity. */
class GuardConnectedPlayerWrites
{
    public function handle(Request $request, Closure $next) {
        if ($request->isMethodSafe() || $request->is('api/player-account-connections*') || !Schema::hasTable('player_account_connections')) return $next($request);
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $request->path().' '.json_encode($request->except(['password','password_confirmation'])), $matches);
        if (!$matches[0]) return $next($request);
        $sources=DB::table('player_account_connections')->whereIn('source_id',array_unique($matches[0]))->whereIn('status',['pending','completed'])->pluck('source_id')->unique()->sort()->values()->all();
        if (!$sources) return $next($request);
        return DB::transaction(function()use($sources,$request,$next){
            DB::table('users')->whereIn('id',$sources)->orderBy('id')->lockForUpdate()->get(['id']);
            if (DB::table('player_account_connections')->whereIn('source_id',$sources)->where('status','completed')->exists()) {
                return response()->json(['message'=>'This roster profile has been connected to an existing account. Refresh the roster and session before scoring. Unsynced records need review; they have not been discarded.','code'=>'PLAYER_PROFILE_CONNECTED'],409);
            }
            return $next($request);
        });
    }
}
