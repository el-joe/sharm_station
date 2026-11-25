@php
    use App\Enums\BookingStatus;
    use Illuminate\Support\Number;
    $eur = fn ($amount) => $amount === null ? '—' : Number::currency((float) $amount, 'EUR', 'en');
    $bookable = $booking->bookable;
    $serviceName = $bookable ? (string) ($bookable->title ?? $bookable->name ?? '') : null;
    $lines = $booking->price_lines ?? [];
@endphp
<x-admin.layout :title="$booking->reference">
    <x-admin.page-header :title="$booking->reference" :breadcrumbs="['Sales' => null, 'Bookings' => route('admin.bookings.index'), $booking->reference => null]"
                         :subtitle="$booking->service_type->label().($serviceName ? ' · '.$serviceName : '').' · created '.$booking->created_at->format('j M Y H:i')">
        <x-slot:actions>
            @if (! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::Completed], true) && $booking->service_type !== \App\Enums\ServiceType::Transfer)
                <a href="{{ route('admin.bookings.edit', $booking) }}" class="btn-ghost btn-sm">Change dates or rooms</a>
            @endif
            @if ($booking->status === BookingStatus::AwaitingPayment && $booking->service_type === \App\Enums\ServiceType::Accommodation)
                <form method="POST" action="{{ route('admin.bookings.release-hold', $booking) }}" data-confirm="Release the hold? The booking is cancelled and its rooms become free." data-confirm-title="Release hold" data-confirm-ok="Release">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm">Release hold</button>
                </form>
            @endif
            <a href="{{ route('admin.bookings.voucher', $booking) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">Voucher PDF</a>
            <a href="{{ route('admin.bookings.voucher', [$booking, 'prices' => 0]) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">Voucher without prices</a>
            <x-admin.status-chip :status="$booking->status" class="text-sm" />
            <x-admin.status-chip :status="$booking->payment_status" class="text-sm" />
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
            <x-admin.card title="Summary">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-3">
                    <div><dt class="text-ink-500">Lead traveller</dt><dd class="font-medium">{{ $booking->lead_name }}</dd></div>
                    <div><dt class="text-ink-500">Email</dt><dd class="font-medium break-all"><a href="mailto:{{ $booking->lead_email }}" class="hover:underline">{{ $booking->lead_email }}</a></dd></div>
                    <div><dt class="text-ink-500">Phone</dt><dd class="font-medium">{{ $booking->lead_phone ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">{{ $booking->check_in ? 'Stay' : 'Travel date' }}</dt>
                        <dd class="font-medium">{{ $booking->check_in ? $booking->check_in->format('j M Y').' → '.$booking->check_out?->format('j M Y') : ($booking->start_date?->format('l j M Y') ?? '—') }}</dd></div>
                    <div><dt class="text-ink-500">Booking type</dt><dd class="font-medium">{{ $booking->booking_type?->label() ?? '—' }} · {{ $booking->pricing_mode?->label() ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Party</dt><dd class="font-medium">{{ $booking->adults }} {{ str('adult')->plural($booking->adults) }}, {{ $booking->children }} {{ str('child')->plural($booking->children) }}</dd></div>
                    <div><dt class="text-ink-500">Season</dt><dd class="font-medium">{{ $booking->season_name ?? 'Base price' }}</dd></div>
                    <div><dt class="text-ink-500">Partner</dt><dd class="font-medium">{{ $booking->partner?->company ?? 'Direct' }}</dd></div>
                    <div><dt class="text-ink-500">Nationality</dt><dd class="font-medium">{{ $booking->nationality_code ?? '—' }}{{ $booking->nationalityGroup ? ' · '.$booking->nationalityGroup->name : '' }}</dd></div>
                    <div><dt class="text-ink-500">Customer currency</dt><dd class="font-medium">{{ $booking->currency }} {{ number_format((float) $booking->total_in_currency, 2) }} <span class="text-ink-500">@ {{ (float) $booking->exchange_rate }}</span></dd></div>
                </dl>
                @if ($booking->notes)
                    <p class="mt-4 rounded-control bg-sand-50 p-3 text-sm text-ink-700"><strong>Notes:</strong> {{ $booking->notes }}</p>
                @endif
            </x-admin.card>

            <x-admin.card title="Price lines" :padding="false">
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[640px]">
                        <thead><tr><th scope="col">Item</th><th scope="col" class="text-right">Qty</th><th scope="col" class="text-right">Unit</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Partner</th><th scope="col">Commission</th></tr></thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr>
                                    <td>{{ $line['label'] ?? '' }}
                                        @if (! empty($line['meta']['season']))<div class="text-xs text-ink-500">Season: {{ is_array($line['meta']['season']) ? ($line['meta']['season']['en'] ?? reset($line['meta']['season'])) : $line['meta']['season'] }}</div>@endif
                                        @if (! empty($line['meta']['date']))<div class="text-xs text-ink-500">{{ $line['meta']['date'] }}</div>@endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ $line['quantity'] ?? 1 }}</td>
                                    <td class="text-right tabular-nums">{{ $eur($line['unit_price'] ?? null) }}</td>
                                    <td class="text-right tabular-nums">{{ $eur($line['total'] ?? null) }}</td>
                                    <td class="text-right tabular-nums">{{ $eur($line['partner_total'] ?? null) }}</td>
                                    <td class="text-xs text-ink-500">{{ isset($line['commission_percent']) ? (float) $line['commission_percent'].'%' : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-ink-500">No price lines stored.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <dl class="grid grid-cols-2 gap-3 border-t border-ink-100 p-5 text-sm sm:grid-cols-4">
                    <div><dt class="text-ink-500">Retail total</dt><dd class="font-semibold tabular-nums">{{ $eur($booking->retail_total) }}</dd></div>
                    <div><dt class="text-ink-500">Partner total</dt><dd class="font-semibold tabular-nums">{{ $eur($booking->partner_total) }}</dd></div>
                    <div><dt class="text-ink-500">Commission{{ $booking->commission_percent !== null ? ' '.(float) $booking->commission_percent.'%' : '' }}</dt><dd class="font-semibold tabular-nums">{{ $eur($booking->commission_amount) }}</dd></div>
                    <div><dt class="text-ink-500">Charged ({{ $booking->price_rule->label() }})</dt><dd class="text-lg font-bold tabular-nums">{{ $eur($booking->total) }}</dd></div>
                </dl>
            </x-admin.card>

            @if ($booking->units->isNotEmpty())
                <x-admin.card title="Units" :padding="false">
                    <div class="overflow-x-auto"><table class="admin-table">
                        <thead><tr><th scope="col">Unit</th><th scope="col" class="text-right">Seats</th><th scope="col" class="text-right">Qty</th><th scope="col" class="text-right">Unit price</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Partner</th></tr></thead>
                        <tbody>@foreach ($booking->units as $unit)
                            <tr><td>{{ $unit->name }}</td><td class="text-right">{{ $unit->seats }}</td><td class="text-right">{{ $unit->quantity }}</td><td class="text-right">{{ $eur($unit->unit_price) }}</td><td class="text-right">{{ $eur($unit->total) }}</td><td class="text-right">{{ $eur($unit->partner_total) }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                </x-admin.card>
            @endif

            @if ($booking->rooms->isNotEmpty())
                <x-admin.card title="Rooms" :padding="false">
                    <div class="overflow-x-auto"><table class="admin-table">
                        <thead><tr><th scope="col">Room</th><th scope="col">Method</th><th scope="col">Occupants</th><th scope="col" class="text-right">Nights</th><th scope="col" class="text-right">Price</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Guests</th></tr></thead>
                        <tbody>@foreach ($booking->rooms as $room)
                            <tr><td>{{ $room->room_name }} @if ($room->room_number)<span class="text-ink-500">#{{ $room->room_number }}</span>@endif</td><td>{{ str($room->pricing_method)->headline() }}</td><td>{{ $room->adults }} ad.@if (! empty($room->child_ages)) + {{ count($room->child_ages) }} ch. ({{ implode(', ', $room->child_ages) }} y)@endif @if ($room->extra_beds) · {{ $room->extra_beds }} extra bed @endif @if ($room->cots) · {{ $room->cots }} cot @endif</td><td class="text-right">{{ $room->nights }}</td><td class="text-right">{{ $eur($room->price) }}</td><td class="text-right">{{ $eur($room->total) }}</td><td class="text-right">{{ $room->guests->count() }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                </x-admin.card>
            @endif

            @if ($booking->addons->isNotEmpty())
                <x-admin.card title="Add-ons" :padding="false">
                    <div class="overflow-x-auto"><table class="admin-table">
                        <thead><tr><th scope="col">Add-on</th><th scope="col">Basis</th><th scope="col" class="text-right">Qty</th><th scope="col" class="text-right">Unit</th><th scope="col" class="text-right">Total</th></tr></thead>
                        <tbody>@foreach ($booking->addons as $addon)
                            <tr><td>{{ $addon->name }}</td><td>{{ str($addon->pricing_basis instanceof \BackedEnum ? $addon->pricing_basis->value : $addon->pricing_basis)->headline() }}</td><td class="text-right">{{ $addon->quantity }}</td><td class="text-right">{{ $eur($addon->unit_price) }}</td><td class="text-right">{{ $eur($addon->total) }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                </x-admin.card>
            @endif

            @if ($booking->includedTransfers->isNotEmpty())
                <x-admin.card title="Included-transfer tasks" subtitle="Part of the package price; arrange the cars outside the system." :padding="false">
                    <div class="overflow-x-auto"><table class="admin-table min-w-[760px]">
                        <thead><tr><th scope="col">Date</th><th scope="col">From → To</th><th scope="col">Vehicle</th><th scope="col">Pax</th><th scope="col">Status and driver</th></tr></thead>
                        <tbody>@foreach ($booking->includedTransfers as $task)
                            <tr>
                                <td class="whitespace-nowrap">{{ $task->date->format('D j M') }} {{ $task->time ? substr((string) $task->time, 0, 5) : '' }}</td>
                                <td>{{ $task->from_place }} → {{ $task->to_place }}</td>
                                <td>{{ $task->vehicle_type?->label() ?? 'Any' }}</td>
                                <td>{{ $task->passengers }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.transfers.included.update', $task) }}" class="flex flex-wrap items-center gap-2" novalidate>
                                        @csrf @method('PATCH')
                                        <select name="status" class="form-control min-h-9 w-40 text-sm" aria-label="Status">
                                            @foreach (\App\Enums\TransferStatus::options() as $value => $label)
                                                <option value="{{ $value }}" @selected($task->status->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="driver_name" value="{{ $task->driver_name }}" placeholder="Driver" aria-label="Driver" class="form-control min-h-9 w-32 text-sm">
                                        <input type="text" name="driver_phone" value="{{ $task->driver_phone }}" placeholder="Phone" aria-label="Driver phone" class="form-control min-h-9 w-28 text-sm">
                                        <input type="text" name="car_plate" value="{{ $task->car_plate }}" placeholder="Plate" aria-label="Plate" class="form-control min-h-9 w-24 text-sm">
                                        <button type="submit" class="btn-link text-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                </x-admin.card>
            @endif

            @if ($booking->transferRequest)
                <x-admin.card title="Transfer request">
                    <p class="text-sm">{{ $booking->transferRequest->hub?->name }} → {{ $booking->transferRequest->destination_name }} · {{ $booking->transferRequest->pickup_at->format('j M Y H:i') }} · {{ $booking->transferRequest->vehicleType?->name }}
                        <x-admin.status-chip :status="$booking->transferRequest->status" />
                        <a href="{{ route('admin.transfers.requests.show', $booking->transferRequest) }}" class="btn-link ml-2">Manage</a></p>
                </x-admin.card>
            @endif

            <x-admin.card title="Payments" :padding="false">
                <div class="overflow-x-auto"><table class="admin-table">
                    <thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Method</th><th scope="col" class="text-right">Amount</th><th scope="col">Status</th><th scope="col">Note</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>@forelse ($booking->payments as $payment)
                        @php($isRefund = $payment->type === \App\Enums\PaymentType::Refund)
                        <tr><td>{{ ($payment->paid_at ?? $payment->created_at)->format('j M Y') }}</td><td>{{ $payment->type->label() }}</td><td>{{ $payment->method?->name ?? 'Manual' }}</td>
                            <td @class(['text-right tabular-nums', 'text-error' => $isRefund])>{{ $isRefund ? '−' : '' }}{{ $eur($payment->amount) }}</td><td><x-admin.status-chip :status="$payment->status" /></td><td class="text-xs text-ink-500">{{ $payment->note }} {{ $payment->transaction_id ? '· '.$payment->transaction_id : '' }} {{ $payment->recorder ? '· '.$payment->recorder->name : '' }}</td>
                            <td class="text-right">
                                @if ($isRefund && $payment->status === \App\Enums\PaymentRecordStatus::Pending)
                                    <form method="POST" action="{{ route('admin.bookings.refunds.paid', [$booking, $payment]) }}" data-confirm="Mark the refund of {{ $eur($payment->amount) }} as paid to the customer?" data-confirm-ok="Mark as paid">
                                        @csrf
                                        <button type="submit" class="btn-link whitespace-nowrap">Mark as paid</button>
                                    </form>
                                @endif
                            </td></tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-ink-500">No payments recorded.</td></tr>
                    @endforelse</tbody>
                </table></div>
                @if ($booking->proofs->isNotEmpty())
                    <div class="border-t border-ink-100 p-5 text-sm">
                        <p class="mb-2 font-semibold">Proofs of payment</p>
                        <ul class="space-y-1">@foreach ($booking->proofs as $proof)
                            <li><a href="{{ route('admin.receipts.show', $proof) }}" class="btn-link">#{{ $proof->id }} · {{ $proof->purpose->label() }} · {{ $proof->created_at->format('j M Y') }}</a> <x-admin.status-chip :status="$proof->status" /></li>
                        @endforeach</ul>
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Travellers" :padding="false">
                <div class="overflow-x-auto"><table class="admin-table">
                    <thead><tr><th scope="col">#</th><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Age</th><th scope="col">Nationality</th></tr></thead>
                    <tbody>@forelse ($booking->travelers as $traveler)
                        <tr><td>{{ $loop->iteration }}</td><td class="font-medium">{{ $traveler->first_name }} {{ $traveler->last_name }}</td><td>{{ $traveler->type instanceof \BackedEnum ? $traveler->type->label() : str($traveler->type)->headline() }}</td><td>{{ $traveler->age ?? '—' }}</td><td>{{ $traveler->nationality ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-500">No traveller details yet.</td></tr>
                    @endforelse</tbody>
                </table></div>
            </x-admin.card>
        </div>

        <aside class="space-y-4">
            @if ($booking->cancellation_requested_at)
                <x-admin.card title="Cancellation request" :subtitle="'Requested '.$booking->cancellation_requested_at->format('j M Y H:i').' by '.($booking->cancellationRequester?->name ?? 'the booking link holder')" :class="$booking->hasPendingCancellationRequest() ? 'border-warning/50' : ''" id="cancellation">
                    <p class="rounded-control bg-sand-50 p-3 text-sm whitespace-pre-line text-ink-900">{{ $booking->cancellation_reason }}</p>
                    @if ($refund)
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Service date</dt><dd>{{ ($booking->start_date ?? $booking->check_in)?->format('j M Y') ?? '—' }}{{ $refund->daysBefore !== null ? ' ('.$refund->daysBefore.' days before)' : '' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Policy</dt><dd class="text-right">{{ $refund->rule }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Paid</dt><dd>{{ $eur($refund->paid) }}</dd></div>
                            <div class="flex justify-between gap-3 border-t border-ink-100 pt-1.5"><dt class="font-medium">Suggested refund</dt><dd class="font-bold">{{ $eur($refund->refund) }}</dd></div>
                        </dl>
                    @endif
                    @if ($booking->hasPendingCancellationRequest())
                        <form method="POST" action="{{ route('admin.bookings.cancellation.approve', $booking) }}" class="mt-4 space-y-3 border-t border-ink-100 pt-4" data-confirm="Cancel booking {{ $booking->reference }} and record the refund?" data-confirm-ok="Approve" data-confirm-title="Approve cancellation" novalidate>
                            @csrf
                            <x-admin.money name="refund_amount" label="Refund to pay (EUR)" :value="$refund?->refund ?? 0" span="" />
                            <x-admin.textarea name="note" label="Note" :rows="2" span="" maxlength="500" />
                            <button type="submit" class="btn-cta w-full">Approve and cancel</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bookings.cancellation.decline', $booking) }}" class="mt-4 space-y-3 border-t border-ink-100 pt-4" novalidate>
                            @csrf
                            <x-admin.textarea name="decline_reason" label="Decline reason" :rows="2" span="" maxlength="500" />
                            <button type="submit" class="btn-ghost w-full">Decline request</button>
                        </form>
                    @else
                        <p class="mt-3 text-sm"><x-admin.status-chip :status="$booking->cancellation_decision" />
                            <span class="text-ink-500">{{ $booking->cancellation_decided_at?->format('j M Y H:i') }}</span></p>
                        @if ($booking->refund_amount !== null)<p class="mt-2 text-sm">Refund to pay: <strong>{{ $eur($booking->refund_amount) }}</strong></p>@endif
                        @if ($booking->cancellation_decision_note)<p class="mt-2 text-sm text-ink-700">{{ $booking->cancellation_decision_note }}</p>@endif
                    @endif
                </x-admin.card>
            @endif

            <x-admin.card title="Payment">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Total</dt><dd class="font-semibold">{{ $eur($booking->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Deposit ({{ (float) $booking->deposit_percent }}%)</dt><dd>{{ $eur($booking->deposit_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Paid</dt><dd>{{ $eur($booking->paidAmount()) }}</dd></div>
                    <div class="flex justify-between border-t border-ink-100 pt-2"><dt class="font-medium">Balance due</dt><dd class="font-bold">{{ $eur($booking->dueAmount()) }}</dd></div>
                </dl>
                @unless (in_array($booking->status, BookingStatus::voided(), true))
                    <form method="POST" action="{{ route('admin.bookings.balance-due', $booking) }}" class="mt-4 flex items-end gap-2 border-t border-ink-100 pt-4" novalidate>
                        @csrf @method('PATCH')
                        <x-admin.input name="balance_due_on" label="Balance due by" type="date" :value="$booking->balance_due_on?->toDateString()" :hint="$booking->balance_due_on ? null : 'Default: '.($balanceDueOn?->format('j M Y') ?? '—')" span="flex-1" />
                        <button type="submit" class="btn-ghost btn-sm mb-0.5">Save</button>
                    </form>
                @endunless
            </x-admin.card>

            @if ($refundable > 0)
                <x-admin.card title="Record refund" :subtitle="'Up to '.$eur($refundable).' can be refunded'.($refundableOnline > 0 ? ' ('.$eur($refundableOnline).' through the online gateway).' : '.')">
                    <form method="POST" action="{{ route('admin.bookings.refunds.store', $booking) }}" class="space-y-3" data-confirm="Record this refund on {{ $booking->reference }}?" data-confirm-ok="Record refund" novalidate>
                        @csrf
                        <x-admin.money name="amount" label="Amount (EUR)" :value="$refundable" span="" />
                        @if ($refundableOnline > 0)
                            <x-admin.switch name="through_gateway" label="Refund through the gateway" description="Sends the money back to the card or PayPal account used online." span="" />
                        @endif
                        <x-admin.input name="paid_at" label="Date" type="date" :value="now()->toDateString()" span="" />
                        <x-admin.select name="payment_method_id" label="Method" :options="$paymentMethods" placeholder="Cash / other" :select2="false" span="" />
                        <x-admin.textarea name="note" label="Note" :rows="2" span="" maxlength="1000" />
                        <button type="submit" class="btn-ghost w-full">Record refund</button>
                    </form>
                </x-admin.card>
            @endif

            @if ($booking->dueAmount() > 0 && ! in_array($booking->status, BookingStatus::voided(), true))
                <x-admin.card title="Record balance payment" subtitle="Paid outside the site (cash, transfer...). The booking becomes fully paid at zero balance.">
                    <form method="POST" action="{{ route('admin.bookings.payments.store', $booking) }}" class="space-y-3" novalidate>
                        @csrf
                        <x-admin.money name="amount" label="Amount (EUR)" :value="$booking->dueAmount()" span="" />
                        <x-admin.input name="paid_at" label="Date" type="date" :value="now()->toDateString()" span="" />
                        <x-admin.select name="payment_method_id" label="Method" :options="$paymentMethods" placeholder="Cash / other" :select2="false" span="" />
                        <input type="hidden" name="type" value="{{ $booking->paidAmount() > 0 ? 'balance' : 'deposit' }}">
                        <x-admin.textarea name="note" label="Note" :rows="2" span="" />
                        <button type="submit" class="btn-cta w-full">Record payment</button>
                    </form>
                </x-admin.card>
            @endif

            <x-admin.card title="Change status">
                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="space-y-3" novalidate>
                    @csrf @method('PATCH')
                    <x-admin.select name="status" label="New status" :options="BookingStatus::options()" :selected="$booking->status" :select2="false" span="" />
                    <x-admin.textarea name="note" label="Note" :rows="2" hint="Required for cancellations and refunds." span="" maxlength="255" />
                    <button type="submit" class="btn-cta w-full">Update status</button>
                </form>
            </x-admin.card>

            <x-admin.card title="Status history">
                <ol class="relative space-y-4 border-l border-ink-100 pl-5">
                    @forelse ($booking->statusLogs as $log)
                        <li class="relative">
                            <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full bg-primary-700 ring-4 ring-white"></span>
                            <p class="text-sm font-medium">{{ $log->from_status?->label() ?? 'New' }} → {{ $log->to_status->label() }}</p>
                            <p class="text-xs text-ink-500">{{ $log->created_at->format('j M Y H:i') }} · {{ $log->user?->name ?? 'System' }}</p>
                            @if ($log->note)<p class="mt-1 text-sm text-ink-700">{{ $log->note }}</p>@endif
                        </li>
                    @empty
                        <li class="text-sm text-ink-500">No status changes yet.</li>
                    @endforelse
                </ol>
            </x-admin.card>
        </aside>
    </div>
</x-admin.layout>
