<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Models\CoachTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Access\AdministrativeAccess;
use App\Services\Access\EntitlementResolver;
use App\Services\DataHub\Contracts\ImportDestinationContract;
use App\Services\DataHub\Enums\ImportSessionType;

final class FmtrxDestination implements ImportDestinationContract
{
    public function __construct(
        private readonly AdministrativeAccess $administration,
        private readonly EntitlementResolver $entitlements,
    ) {
    }

    public function supportedSessionTypes(): array
    {
        return ImportSessionType::cases();
    }

    public function validateDestination(User $user, Team $team, ImportSessionType $sessionType): bool
    {
        if ( ! in_array($sessionType, $this->supportedSessionTypes(), true)) {
            return false;
        }
        if ($this->administration->canManageSubscriptions($user)) {
            return true;
        }
        if ('coach' !== (string) $user->type) {
            return false;
        }
        if ( ! CoachTeam::query()->where('coach_id', $user->id)->where('team_id', $team->id)->exists()) {
            return false;
        }

        return $this->entitlements->hasEntitlement($user, 'data_hub_import', (string) $team->id);
    }
}
