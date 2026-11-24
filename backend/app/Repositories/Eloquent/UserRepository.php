<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Customer\CustomerFilterData;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', strtolower($email))->first();
    }

    public function customersQuery(CustomerFilterData $filters): Builder
    {
        $valid = fn (Builder $bookings) => $bookings->whereNotIn('status', BookingStatus::voided());

        return $this->query()
            ->select('users.*')
            ->where('role', UserRole::Customer)
            ->withCount('bookings')
            ->withSum(['bookings as total_spent' => $valid], 'total')
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")
                ->orWhereRaw("REPLACE(users.phone, ' ', '') like ?", ['%'.preg_replace('/\s+/', '', $search).'%'])))
            ->when($filters->nationalityCode, fn (Builder $query, string $code) => $query->where('nationality_code', $code))
            ->when($filters->active !== null, fn (Builder $query) => $query->where('is_active', $filters->active))
            ->when($filters->withBookings === true, fn (Builder $query) => $query->has('bookings'))
            ->when($filters->withBookings === false, fn (Builder $query) => $query->doesntHave('bookings'));
    }

    public function staffQuery(?UserRole $role = null): Builder
    {
        return $this->query()
            ->select('users.*')
            ->whereIn('role', $role !== null ? [$role] : UserRole::staff());
    }

    public function countActiveWithRole(UserRole $role): int
    {
        return $this->query()->where('role', $role)->where('is_active', true)->count();
    }
}
