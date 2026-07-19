<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\User;
use BackedEnum;

class AdministrativeAccess
{
    public function canManageSubscriptions(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        $type = $user->type instanceof BackedEnum ? $user->type->value : (string) $user->type;
        if (in_array($type, ['admin', 'super_admin'], true)) {
            return true;
        }

        // Compatibility boundary for the existing administrator account.
        // Clients receive only the resulting capability, never this allow-list.
        $email = mb_strtolower(trim((string) $user->email));
        $adminEmails = array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            config('access.admin_emails', [])
        );

        return '' !== $email && in_array($email, $adminEmails, true);
    }

    /** @return array{subscription_admin: bool} */
    public function capabilities(?User $user): array
    {
        return ['subscription_admin' => $this->canManageSubscriptions($user)];
    }
}
