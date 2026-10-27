<?php
namespace Tests\Concerns;
trait AcceptsPlayerPolicies
{
    private function policyAgreement(): array
    {
        return ['policy_accepted' => true, 'terms_version' => config('player_consent.terms_version'), 'privacy_version' => config('player_consent.privacy_version'), 'acknowledgment_version' => config('player_consent.acknowledgment_version')];
    }
    private function completePolicySetup(string $token, string $born = '2000-01-01'): void
    {
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => $born, ...$this->policyAgreement()])->assertOk()->assertJsonPath('next_action', 'credentials');
    }
}
