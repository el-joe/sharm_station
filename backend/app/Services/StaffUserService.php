<?php

namespace App\Services;

use App\DTOs\Staff\StaffUserData;
use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Exceptions\DomainValidationException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Staff accounts and roles (A5): super admins, agents and editors. Nobody can lock themselves out,
 * and the last active super admin can never be demoted, deactivated or deleted.
 */
class StaffUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return Builder<User>
     */
    public function adminQuery(?UserRole $role = null): Builder
    {
        return $this->users->staffQuery($role);
    }

    public function isStaff(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(StaffUserData $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'role' => $data->role,
                'is_active' => $data->isActive,
                'password' => $data->password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->activity->log(ActivityAction::Created, $user, "Staff user {$user->email} created ({$data->role->label()})", ['role' => $data->role->value], $actor);

            return $user;
        });
    }

    /**
     * @throws DomainValidationException when the change would lock out the actor or the last super admin.
     */
    public function update(User $user, StaffUserData $data, User $actor): User
    {
        $losesSuperAdmin = $user->role === UserRole::SuperAdmin && $user->isActive() && ($data->role !== UserRole::SuperAdmin || ! $data->isActive);

        if ($user->is($actor) && ($data->role !== $user->role || ! $data->isActive)) {
            throw DomainValidationException::withErrors(['role' => ['You cannot change your own role or deactivate yourself.']]);
        }

        if ($losesSuperAdmin && $this->users->countActiveWithRole(UserRole::SuperAdmin) <= 1) {
            throw DomainValidationException::withErrors(['role' => ['This is the last active super admin: promote another user first.']]);
        }

        return DB::transaction(function () use ($user, $data, $actor): User {
            $before = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role?->value, 'is_active' => $user->isActive()];
            $attributes = ['name' => $data->name, 'email' => $data->email, 'phone' => $data->phone, 'role' => $data->role, 'is_active' => $data->isActive];

            if ($data->password !== null) {
                $attributes['password'] = $data->password;
            }

            $user = $this->users->update($user, $attributes);

            if (! $data->isActive) {
                $user->tokens()->delete();
            }

            $after = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role?->value, 'is_active' => $user->isActive()];
            $this->activity->log(ActivityAction::Updated, $user, "Staff user {$user->email} updated", ['changes' => ['old' => array_diff_assoc($before, $after), 'new' => array_diff_assoc($after, $before)]], $actor);

            if ($data->password !== null) {
                $this->activity->log(ActivityAction::PasswordChanged, $user, "Password of {$user->email} set by an administrator", causer: $actor);
            }

            return $user;
        });
    }

    /**
     * @throws DomainValidationException
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw DomainValidationException::withErrors(['user' => ['You cannot delete your own account.']]);
        }

        if ($user->role === UserRole::SuperAdmin && $user->isActive() && $this->users->countActiveWithRole(UserRole::SuperAdmin) <= 1) {
            throw DomainValidationException::withErrors(['user' => ['The last active super admin cannot be deleted.']]);
        }

        DB::transaction(function () use ($user, $actor): void {
            $this->activity->log(ActivityAction::Deleted, null, "Staff user {$user->email} deleted", ['user_id' => $user->id, 'role' => $user->role?->value], $actor);
            $user->tokens()->delete();
            $this->users->delete($user);
        });
    }
}
