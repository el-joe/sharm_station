<x-admin.layout title="Google reviews">
    <x-admin.page-header title="Google reviews" :breadcrumbs="['Sales' => null, 'Google reviews' => null]"
                         subtitle="Read from Google Places every 12 hours and cached. You can hide a review from the site but not edit it.">
        <x-slot:actions>
            @if ($placeId)
                <button type="button" class="btn-cta" data-ajax-action="{{ route('admin.google-reviews.sync', ['place_id' => $placeId]) }}" data-busy="Syncing…" data-reload>
                    <x-admin.icon name="refresh" size="18" /> Sync now
                </button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-admin.card title="Business Place ID" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.google-reviews.place') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" novalidate>
                @csrf @method('PUT')
                <x-admin.input name="place_id" label="Google Place ID of the business profile" :value="$businessPlaceId" placeholder="ChIJ…" span="flex-1" />
                <button type="submit" class="btn-primary">Save</button>
            </form>
            @if (count($places) > 1)
                <div class="mt-5">
                    <p class="form-label">Show reviews of</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($places as $id => $label)
                            <a href="{{ route('admin.google-reviews.index', ['place_id' => $id]) }}" @if ($id === $placeId) aria-current="page" @endif
                               class="rounded-pill border px-3 py-1.5 text-sm {{ $id === $placeId ? 'border-primary-700 bg-primary-700 text-white' : 'border-ink-300 bg-white text-ink-700 hover:bg-ink-100' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Summary">
            @if ($summary['place_id'])
                <p class="text-4xl font-bold">{{ $summary['rating'] ?? '—' }} <span class="text-2xl text-gold-500">★</span></p>
                <p class="mt-1 text-sm text-ink-700">{{ number_format($summary['total']) }} reviews on Google</p>
                <p class="mt-1 text-xs text-ink-500">Last sync: {{ $summary['synced_at'] ? \Carbon\CarbonImmutable::parse($summary['synced_at'])->diffForHumans() : 'never' }}</p>
                <p class="mt-1 text-xs text-ink-500">{{ $items->where('is_hidden', true)->count() }} hidden · {{ $items->count() }} cached</p>
                <a href="{{ $summary['write_review_url'] }}" target="_blank" rel="noopener" class="btn-link mt-3">Write a review link <x-admin.icon name="external" size="14" /></a>
            @else
                <p class="text-sm text-ink-700">Set the business Place ID to start syncing reviews.</p>
            @endif
        </x-admin.card>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($items as $review)
            <article data-toggle-scope @class(['card flex flex-col p-5 transition', 'opacity-50' => $review->is_hidden])>
                <header class="flex items-center gap-3">
                    @if ($review->author_photo_url)
                        <img src="{{ $review->author_photo_url }}" alt="" class="size-10 rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <span class="grid size-10 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700">{{ mb_substr($review->author_name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $review->author_name }}</p>
                        <p class="text-xs text-ink-500"><span class="text-gold-500" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}<span class="text-ink-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span> · {{ $review->published_at?->diffForHumans() }} @if ($review->language)· {{ strtoupper($review->language) }}@endif</p>
                    </div>
                </header>
                <p class="mt-3 line-clamp-6 flex-1 text-sm text-ink-700">{{ $review->text ?: 'No text, rating only.' }}</p>
                <footer class="mt-4 flex items-center justify-between border-t border-ink-100 pt-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                        <input type="checkbox" class="size-4 accent-primary-700" data-ajax-toggle="{{ route('admin.google-reviews.update', $review) }}" data-field="hidden" @checked($review->is_hidden)>
                        Hide from site
                    </label>
                    @if ($review->author_url)<a href="{{ $review->author_url }}" target="_blank" rel="noopener" class="text-xs text-ink-500 hover:underline">View on Google</a>@endif
                </footer>
            </article>
        @empty
            <x-admin.card class="md:col-span-2 xl:col-span-3"><p class="py-6 text-center text-sm text-ink-500">No cached reviews yet. Use “Sync now”.</p></x-admin.card>
        @endforelse
    </div>
</x-admin.layout>
