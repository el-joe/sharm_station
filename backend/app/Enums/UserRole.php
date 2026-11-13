<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case Agent = 'agent';
    case Editor = 'editor';
    case Customer = 'customer';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Agent => 'Agent',
            self::Editor => 'Editor',
            self::Customer => 'Customer',
            self::Partner => 'Partner',
        };
    }

    /**
     * Roles allowed into the admin panel.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::SuperAdmin, self::Agent, self::Editor];
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }

    /**
     * Editors manage content only: no prices, partner prices, payments or bookings money.
     */
    public function canManagePrices(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Agent], true);
    }

    public function canManageSettings(): bool
    {
        return $this === self::SuperAdmin;
    }
}
