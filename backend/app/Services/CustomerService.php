<?php

namespace App\Services;

use App\DTOs\Customer\CustomerFilterData;
use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customers (A2, SRS 14.1): website accounts with the customer role, their bookings, internal notes and activation.
 */
class CustomerService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return Builder<User>
     */
    public function adminQuery(CustomerFilterData $filters): Builder
    {
        return $this->users->customersQuery($filters);
    }

    public function isCustomer(User $user): bool
    {
        return $user->role === UserRole::Customer;
    }

    /**
     * Customer with totals for the detail page.
     */
    public function findForAdmin(User $user): User
    {
        return $this->users->customersQuery(new CustomerFilterData)->whereKey($user->id)->firstOrFail();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function bookings(User $customer): Collection
    {
        return $customer->bookings()->with('bookable')->latest('id')->get();
    }

    public function setActive(User $customer, bool $active, User $actor): User
    {
        $customer = $this->users->update($customer, ['is_active' => $active]);

        if (! $active) {
            $customer->tokens()->delete();
        }

        $this->activity->log($active ? ActivityAction::Activated : ActivityAction::Deactivated, $customer, 'Customer '.$customer->email.($active ? ' activated' : ' deactivated'), causer: $actor);

        return $customer;
    }

    public function updateNotes(User $customer, ?string $notes, User $actor): User
    {
        $customer = $this->users->update($customer, ['admin_notes' => $notes]);
        $this->activity->log(ActivityAction::NotesUpdated, $customer, 'Notes of customer '.$customer->email.' updated', causer: $actor);

        return $customer;
    }
}
