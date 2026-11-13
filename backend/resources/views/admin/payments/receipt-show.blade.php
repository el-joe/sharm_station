@php
    use App\Enums\ProofStatus;
    $booking = $proof->booking;
    $title = 'Proof for '.$booking->reference;
    $eur = fn ($amount) => '€'.number_format((float) $amount, 2);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Sales' => null, 'Receipts review' => route('admin.receipts.index'), $title => null]"
                         :subtitle="$booking->lead_name.' · '.$proof->method?->name.' · uploaded '.$proof->created_at->format('j M Y H:i')">
        <x-slot:actions>
            <x-admin.status-chip :status="$proof->status" class="text-sm" />
            <a href="{{ route('admin.bookings.show', $booking) }}" class="btn-primary">Open booking</a>
        </x-slot:actions>
    </x-admin.page-header>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
            @foreach ($proof->files as $index => $file)
                @php($url = route('admin.receipts.file', [$proof, $index]))
                <x-admin.card :title="$file['name']" :subtitle="$file['mime'].' · '.number_format($file['size'] / 1024, 0).' KB'">
                    <x-slot:actions><a href="{{ $url }}?download=1" class="btn-link">Download</a></x-slot:actions>
                    @if (str_starts_with($file['mime'], 'image/'))
                        <img src="{{ $url }}" alt="Proof of payment {{ $index + 1 }}" class="max-h-[640px] w-auto rounded-control border border-ink-100">
                    @else
                        <iframe src="{{ $url }}" title="Proof of payment {{ $index + 1 }}" class="h-[640px] w-full rounded-control border border-ink-100"></iframe>
                    @endif
                </x-admin.card>
            @endforeach
        </div>
        <aside class="space-y-4">
            <x-admin.card title="Declared by the customer">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Transaction no.</dt><dd>{{ $proof->transaction_number ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Date</dt><dd>{{ $proof->transaction_date?->format('j M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Amount</dt><dd>{{ $proof->amount !== null ? number_format((float) $proof->amount, 2).' '.$booking->currency : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Sender</dt><dd>{{ $proof->sender_name ?? '—' }}</dd></div>
                    <div class="flex justify-between border-t border-ink-100 pt-2"><dt class="text-ink-500">Expected {{ $proof->purpose->label() }}</dt>
                        <dd class="font-semibold">{{ $eur($proof->purpose->value === 'deposit' ? $booking->deposit_amount : $booking->dueAmount()) }} <span class="text-ink-500">({{ number_format(($proof->purpose->value === 'deposit' ? (float) $booking->deposit_amount : $booking->dueAmount()) * (float) $booking->exchange_rate, 2) }} {{ $booking->currency }})</span></dd></div>
                </dl>
            </x-admin.card>
            @if ($proof->status === ProofStatus::Pending)
                <x-admin.card title="Decision">
                    <form method="POST" action="{{ route('admin.receipts.approve', $proof) }}" data-confirm="Approve this proof? The {{ strtolower($proof->purpose->label()) }} is recorded and the booking confirmed." data-confirm-title="Approve payment proof?" data-confirm-ok="Approve" data-confirm-tone="primary">
                        @csrf
                        <button type="submit" class="btn-cta w-full">Approve and confirm booking</button>
                    </form>
                    <form method="POST" action="{{ route('admin.receipts.reject', $proof) }}" class="mt-5 space-y-3" novalidate>
                        @csrf
                        <x-admin.textarea name="rejection_reason" label="Rejection reason" :rows="3" span="" hint="Sent to the customer, who can upload a new proof." />
                        <button type="submit" class="btn-ghost w-full text-error">Reject</button>
                    </form>
                </x-admin.card>
            @else
                <x-admin.card title="Decision">
                    <p class="text-sm">{{ $proof->status->label() }} by {{ $proof->reviewer?->name ?? '—' }} on {{ $proof->reviewed_at?->format('j M Y H:i') }}.</p>
                    @if ($proof->rejection_reason)<p class="mt-2 text-sm text-error">{{ $proof->rejection_reason }}</p>@endif
                </x-admin.card>
            @endif
        </aside>
    </div>
</x-admin.layout>
