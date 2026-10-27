<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;
use App\Http\Controllers\Controller;
use App\Services\Consent\PlayerConsentService;
use Illuminate\Http\Request;
class PlayerConsentController extends Controller
{
    public function policies(PlayerConsentService $service)
    {
        return response()->json($service->policies())->header('Cache-Control', 'no-store');
    }
    public function setup(Request $request, PlayerConsentService $service)
    {
        $data = $request->validate([
            'born_date' => 'required|date_format:Y-m-d|before_or_equal:today|after:1900-01-01',
            'policy_accepted' => 'sometimes|boolean',
            'terms_version' => 'sometimes|string|max:40', 'privacy_version' => 'sometimes|string|max:40',
            'acknowledgment_version' => 'sometimes|string|max:40',
        ]);
        $tokenName = (string) $request->user()->currentAccessToken()?->name;
        if (str_starts_with($tokenName, 'profile_claim_invitation:')) {
            $invite = \App\Models\TeamInvitation::find(substr($tokenName, strlen('profile_claim_invitation:')));
            abort_unless($invite && $invite->state() === 'pending' && $invite->user_id === $request->user()->id, 422, 'This invitation is no longer available.');
        }
        return response()->json($service->setup($request->user(), $data, $request))->header('Cache-Control', 'no-store');
    }
}
