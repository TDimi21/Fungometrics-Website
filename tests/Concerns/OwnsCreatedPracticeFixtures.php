<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Practice;

/**
 * Opt-in ownership for legacy result happy-path fixtures (including sessions
 * created inside result factories). Authorization tests must set their own
 * owners and deliberately do not use this trait.
 */
trait OwnsCreatedPracticeFixtures
{
    protected function setUpOwnsCreatedPracticeFixtures(): void
    {
        Practice::creating(function (Practice $practice): void {
            if (null === $practice->user_id && auth()->id()) {
                $practice->user_id = auth()->id();
            }
        });
    }
}
