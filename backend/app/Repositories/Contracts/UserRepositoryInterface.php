<?php

namespace App\Repositories\Contracts;

use App\DTOs\Customer\CustomerFilterData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<User>
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /**
     * Customers with their bookings count and total spent (EUR, cancelled and refunded bookings excluded).
     *
     * @return Builder<User>
     */
    public function customersQuery(CustomerFilterData $filters): Builder;

    /**
     * Staff accounts (super admins, agents, editors).
     *
     * @return Builder<User>
     */
    public function staffQuery(?UserRole $role = null): Builder;

    public function countActiveWithRole(UserRole $role): int;
}
