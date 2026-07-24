<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Contracts\ImportDestinationContract;
use App\Services\DataHub\Enums\ImportSessionType;

final readonly class DestinationRegistry
{
    public function __construct(private ImportDestinationContract $destination)
    {
    }

    /** @return array<int, ImportSessionType> */
    public function all(): array
    {
        return $this->destination->supportedSessionTypes();
    }

    public function allows(User $user, Team $team, ImportSessionType $sessionType): bool
    {
        return $this->destination->validateDestination($user, $team, $sessionType);
    }
}
