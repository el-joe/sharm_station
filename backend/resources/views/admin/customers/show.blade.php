@php
    use Illuminate\Support\Number;
    $eur = fn ($amount) => Number::currency((float) $amount, 'EUR', 'en');
@endphp
<x-admin.layout :title="$customer->name">
    <x-admin.page-header :title="$customer->name" :breadcrumbs="['Sales' => null, 'Customers' => route('admin.customers.index'), $customer->name => null]"
                         :subtitle="'Customer since '.$customer->created_at->format('j M Y')">
        <x-slot:actions>
            <x-admin.status-chip :status="$customer->isActive() ? 'active' : 'inactive'" :label="$customer->isActive() ? 'Active' : 'Deactivated'" />
            <form method="POST" action="{{ route('admin.customers.toggle-active', $customer) }}"
                  data-confirm="{{ $customer->isActive() ? 'Deactivate '.$customer->name.'? They will be signed out and cannot sign in until reactivated.' : 'Reactivate '.$customer->name.'?' }}"
                  data-confirm-ok="{{ $customer->isActive() ? 'Deactivate' : 'Activate' }}" data-confirm-tone="{{ $customer->isActive() ? 'danger' : 'primary' }}">
                @csrf @method('PATCH')
                <button type="submit" class="{{ $customer->isActive() ? 'btn-danger' : 'btn-primary' }}">{{ $customer->isActive() ? 'Deactivate' : 'Activate' }}</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.kpi label="Bookings" :value="$customer->bookings_count" icon="calendar" />
        <x-admin.kpi label="Total spent" :value="$eur($customer->total_spent ?? 0)" icon="euro" hint="Cancelled and refunded excluded" />
        <x-admin.kpi label="Last booking" :value="$bookings->first()?->created_at?->format('j M Y') ?? '—'" icon="clock" />
        <x-admin.kpi label="Newsletter" :value="$customer->newsletter ? 'Subscribed' : 'No'" icon="bell" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-admin.card title="Bookings" :padding="false">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[720px]">
                    <thead><tr><th scope="col">Reference</th><th scope="col">Service</th><th scope="col">Date</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Balance</th><th scope="col">Status</th><th scope="col">Payment</th></tr></thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr>
                                <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-semibold text-primary-700 hover:underline">{{ $booking->reference }}</a></td>
                                <td>{{ $booking->service_type->label() }}<div class="text-xs text-ink-500">{{ $booking->bookable ? (string) ($booking->bookable->title ?? $booking->bookable->name ?? '') : '' }}</div></td>
                                <td>{{ ($booking->start_date ?? $booking->check_in)?->format('j M Y') ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $eur($booking->total) }}</td>
                                <td class="text-right tabular-nums">{{ $eur($booking->dueAmount()) }}</td>
                                <td><x-admin.status-chip :status="$booking->status" />@if ($booking->hasPendingCancellationRequest())<div class="mt-1"><x-admin.status-chip status="cancellation_requested" label="Cancellation requested" tone="warning" /></div>@endif</td>
                                <td><x-admin.status-chip :status="$booking->payment_status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-ink-500">No bookings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>

        <aside class="space-y-4">
            <x-admin.card title="Profile">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-ink-500">Email</dt><dd class="font-medium break-all"><a href="mailto:{{ $customer->email }}" class="hover:underline">{{ $customer->email }}</a></dd></div>
                    <div><dt class="text-ink-500">Phone</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Nationality</dt><dd class="font-medium">{{ $customer->nationality_code ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Language and currency</dt><dd class="font-medium">{{ strtoupper($customer->locale ?? 'en') }} · {{ $customer->currency ?? 'EUR' }}</dd></div>
                    <div><dt class="text-ink-500">Email verified</dt><dd class="font-medium">{{ $customer->email_verified_at?->format('j M Y') ?? 'No' }}</dd></div>
                </dl>
            </x-admin.card>
            <x-admin.card title="Internal notes" subtitle="Only visible to the team.">
                <form method="POST" action="{{ route('admin.customers.notes', $customer) }}" class="space-y-3" novalidate>
                    @csrf @method('PUT')
                    <x-admin.textarea name="admin_notes" :value="$customer->admin_notes" :rows="5" span="" maxlength="5000" aria-label="Internal notes" />
                    <button type="submit" class="btn-primary w-full">Save notes</button>
                </form>
            </x-admin.card>
        </aside>
    </div>
</x-admin.layout>
