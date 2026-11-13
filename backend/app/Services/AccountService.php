<?php

namespace App\Services;

use App\DTOs\Account\ProfileData;
use App\Exceptions\DomainValidationException;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * Customer account (site): own bookings, profile and password.
 */
class AccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly BookingRepositoryInterface $bookings,
    ) {}

    public function bookings(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookings->paginateForUser($user->id, $perPage);
    }

    public function updateProfile(User $user, ProfileData $data): User
    {
        return $this->users->update($user, $data->attributes);
    }

    /**
     * @throws DomainValidationException when the current password is wrong.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw DomainValidationException::withErrors(['current_password' => ['The current password is not correct.']]);
        }

        $this->users->update($user, ['password' => $newPassword]);
    }
}
