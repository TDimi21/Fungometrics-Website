<?php

declare(strict_types=1);

namespace App\Services\DataHub\Contracts;

use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Enums\ImportSessionType;

interface ImportDestinationContract
{
    /** @return array<int, ImportSessionType> */
    public function supportedSessionTypes(): array;

    public function validateDestination(User $user, Team $team, ImportSessionType $sessionType): bool;
}
