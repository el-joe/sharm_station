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

    /**
     * Pages, blog and FAQ: every staff role (editors manage content only).
     */
    public function canManageContent(): bool
    {
        return $this->isStaff();
    }

    /**
     * Sales, finance and partner reports.
     */
    public function canViewReports(): bool
    {
        return $this->canManagePrices();
    }

    /**
     * Staff accounts, roles and the activity log.
     */
    public function canManageUsers(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Read-only permissions matrix shown in Users and roles: module => [role value => allowed].
     *
     * @return array<string, array<string, bool>>
     */
    public static function permissionMatrix(): array
    {
        $modules = [
            'Dashboard and catalogue (trips, packages, add-ons, destinations)' => fn (self $role): bool => $role->isStaff(),
            'Accommodation (buildings, rooms, calendar)' => fn (self $role): bool => $role->isStaff(),
            'Content (pages, blog, FAQ, Google reviews, AI suggestions)' => fn (self $role): bool => $role->canManageContent(),
            'Prices and price lists' => fn (self $role): bool => $role->canManagePrices(),
            'Bookings, payments and receipts' => fn (self $role): bool => $role->canManagePrices(),
            'Customers, partners, coupons and transfers' => fn (self $role): bool => $role->canManagePrices(),
            'Reports' => fn (self $role): bool => $role->canViewReports(),
            'Settings and MCP access' => fn (self $role): bool => $role->canManageSettings(),
            'Users and roles, activity log' => fn (self $role): bool => $role->canManageUsers(),
        ];

        return collect($modules)->map(fn (\Closure $check): array => collect(self::staff())
            ->mapWithKeys(fn (self $role): array => [$role->value => $check($role)])->all())->all();
    }
}
