@php
    use App\Enums\PartnerStatus;
    $overrideRows = collect($commissions)->filter(fn ($row) => $row['source'] === 'partner_override');
@endphp
<x-admin.layout :title="$partner->company">
    <x-admin.page-header :title="$partner->company" :breadcrumbs="['Sales' => null, 'Partners' => route('admin.partners.index'), $partner->company => null]"
                         :subtitle="collect([$partner->country, $partner->approved_at ? 'partner since '.$partner->approved_at->format('j M Y') : null, $partner->contact_name ?? $partner->user?->name ? 'contact '.($partner->contact_name ?? $partner->user?->name) : null])->filter()->implode(' · ')">
        <x-slot:actions>
            <x-admin.status-chip :status="$partner->status" />
            @if ($partner->status === PartnerStatus::Active)
                <form method="POST" action="{{ route('admin.partners.suspend', $partner) }}" data-confirm="Suspend {{ $partner->company }}?">@csrf<button type="submit" class="btn-primary">Suspend</button></form>
            @else
                <form method="POST" action="{{ route('admin.partners.approve', $partner) }}">@csrf<button type="submit" class="btn-primary">{{ $partner->status === PartnerStatus::Suspended ? 'Reactivate' : 'Approve' }}</button></form>
            @endif
            <button type="submit" form="partner-commission-form" class="btn-cta">Save changes</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div data-tabs data-tabs-hash>
        <div role="tablist" aria-label="Partner sections" class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-ink-300/60">
            <button type="button" role="tab" id="tab-overview-tab" aria-controls="tab-overview" aria-selected="true" class="tab-link">Overview</button>
            <button type="button" role="tab" id="tab-commissions-tab" aria-controls="tab-commissions" aria-selected="false" class="tab-link">Commissions</button>
        </div>

        <div role="tabpanel" id="tab-overview" aria-labelledby="tab-overview-tab" class="space-y-4">
            @if ($partner->status === PartnerStatus::Rejected)
                <div class="card border-error/30 bg-error/5 p-5" role="status">
                    <p class="text-sm font-semibold text-error">Application rejected{{ $partner->rejected_at ? ' on '.$partner->rejected_at->format('j M Y') : '' }}</p>
                    <p class="mt-1 text-sm whitespace-pre-line text-ink-900">{{ $partner->rejection_reason }}</p>
                    <p class="mt-2 text-xs text-ink-500">The applicant keeps a customer account and cannot open the partner panel. Approve to activate the partner anyway.</p>
                </div>
            @endif
            @if ($partner->status === PartnerStatus::Pending)
                <x-admin.card title="Reject the application" subtitle="The reason is stored on the partner and is ready to be emailed to the applicant.">
                    <form method="POST" action="{{ route('admin.partners.reject', $partner) }}" class="space-y-3" data-confirm="Reject the application of {{ $partner->company }}?" data-confirm-ok="Reject" novalidate>
                        @csrf
                        <x-admin.textarea name="rejection_reason" label="Reason" :rows="3" span="" maxlength="1000" placeholder="e.g. We could not verify your travel agency licence." />
                        <button type="submit" class="btn-danger">Reject application</button>
                    </form>
                </x-admin.card>
            @endif
            @if ($partner->message)
                <x-admin.card title="Application message"><p class="text-sm whitespace-pre-line text-ink-700">{{ $partner->message }}</p></x-admin.card>
            @endif
            <x-admin.card title="Company">
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-ink-500">Contact</dt><dd class="font-medium">{{ $partner->contact_name ?? $partner->user?->name }}</dd></div>
                    <div><dt class="text-ink-500">Email</dt><dd class="font-medium">{{ $partner->user?->email }}</dd></div>
                    <div><dt class="text-ink-500">Phone</dt><dd class="font-medium">{{ $partner->phone ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Tax number</dt><dd class="font-medium">{{ $partner->tax_number ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Country</dt><dd class="font-medium">{{ $partner->country ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Website</dt><dd class="font-medium">{{ $partner->website ?? '—' }}</dd></div>
                </dl>
            </x-admin.card>
            <x-admin.card title="Bookings">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
                    <x-admin.switch name="coupons_allowed" label="Coupons allowed" description="Lets this partner apply coupon codes that are open to partners. Saved immediately."
                                    :checked="$partner->coupons_allowed" data-ajax-toggle="{{ route('admin.partners.coupons', $partner) }}" data-field="coupons_allowed" span="md:col-span-6" />
                </div>
            </x-admin.card>
        </div>

        <div role="tabpanel" id="tab-commissions" aria-labelledby="tab-commissions-tab" hidden>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)]">
                <form id="partner-commission-form" method="POST" action="{{ route('admin.partners.commissions.override', $partner) }}" novalidate>
                    @csrf @method('PUT')
                    <x-admin.card title="Commission per type" subtitle="Leave empty to use the standard (global) rate. Percentages only." :padding="true">
                        <div class="overflow-x-auto">
                            <table class="admin-table min-w-[560px]">
                                <thead>
                                    <tr><th scope="col">Type</th><th scope="col">Standard rate</th><th scope="col">Partner override</th><th scope="col">Effective rate</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($commissions as $row)
                                        @php($key = "commissions.{$row['category']}")
                                        <tr @class(['bg-primary-100/40' => $row['source'] === 'partner_override'])>
                                            <th scope="row" class="px-4 py-3 text-left font-medium">{{ $row['label'] }}</th>
                                            <td class="tabular-nums text-ink-700">{{ $row['standard_percent'] !== null ? (float) $row['standard_percent'].'%' : '—' }}</td>
                                            <td>
                                                <div class="relative w-40">
                                                    <input type="number" step="0.01" min="0" max="100" name="commissions[{{ $row['category'] }}]"
                                                           aria-label="{{ $row['label'] }} override"
                                                           value="{{ old($key, $row['override_percent']) }}" placeholder="Use standard"
                                                           @class(['form-control pr-8 tabular-nums', 'is-invalid' => $errors->has($key)])>
                                                    <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">%</span>
                                                </div>
                                                @error($key)<p class="form-error">{{ $message }}</p>@enderror
                                            </td>
                                            <td class="whitespace-nowrap">
                                                <strong class="tabular-nums">{{ (float) $row['percent'] }}%</strong>
                                                <span @class(['chip ml-1', 'bg-partner-100 text-partner-600' => $row['source'] === 'partner_override', 'bg-ink-100 text-ink-700' => $row['source'] !== 'partner_override'])>
                                                    {{ match ($row['source']) { 'partner_override' => 'Override', 'global_setting' => 'Standard', default => 'None' } }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="text-ink-500">
                                        <th scope="row" class="px-4 py-3 text-left font-medium">Accommodation</th>
                                        <td>—</td>
                                        <td>Not available</td>
                                        <td><span class="chip bg-ink-100 text-ink-700">No commission</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-4 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
                            <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
                            @if ($overrideRows->isEmpty())
                                This partner uses the standard rates for every type. Changes apply to new bookings only.
                            @else
                                {{ $overrideRows->map(fn ($row) => $row['label'].' uses this partner’s override ('.(float) $row['percent'].' %) instead of the standard '.((float) ($row['standard_percent'] ?? 0)).' %')->implode('; ') }}. Changes apply to new bookings only.
                            @endif
                        </p>
                        <div class="mt-4 flex justify-end"><button type="submit" class="btn-cta">Save commissions</button></div>
                    </x-admin.card>
                </form>

                <x-admin.card title="Resolution order">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-700">
                        <li>Partner override (this page)</li>
                        <li>Global setting (<a href="{{ route('admin.commissions.edit') }}" class="underline">Settings → Commissions</a>)</li>
                        <li>0 % if neither is set</li>
                    </ol>
                </x-admin.card>
            </div>
        </div>
    </div>
</x-admin.layout>
