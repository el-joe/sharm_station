@php
    use App\DTOs\Ai\SeoValuesData;
    $payload = $suggestion->payload ?? [];
    $pending = $suggestion->isPending();
    $title = 'SEO suggestion · '.$suggestion->title();
    $labels = ['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'keywords' => 'Keywords', 'og_title' => 'OG title', 'og_description' => 'OG description'];
    $limits = ['meta_title' => 70, 'meta_description' => 170, 'og_title' => 95, 'og_description' => 200];
    $text = fn ($value): string => is_array($value) ? implode(', ', $value) : (string) ($value ?? '');
    $locales = ['en' => 'English', 'it' => 'Italian'];
    $changes = [];
    foreach ($locales as $locale => $language) {
        $changes[$locale] = collect(SeoValuesData::FIELDS)->filter(fn ($field) => filled($payload['locales'][$locale][$field] ?? null)
            && $text($payload['locales'][$locale][$field]) !== $text($current['locales'][$locale][$field] ?? null))->count();
    }
    $slugChanged = filled($payload['slug'] ?? null) && ($payload['slug'] ?? null) !== ($current['slug'] ?? null);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Content' => null, 'AI suggestions' => route('admin.ai-suggestions.index'), '#'.$suggestion->id => null]">
        <x-slot:subtitle>@include('admin.ai-suggestions.partials.meta')</x-slot:subtitle>
        <x-slot:actions>
            <x-admin.status-chip :status="$suggestion->status" class="text-sm" />
            @if ($subjectUrl)<a href="{{ $subjectUrl }}" class="btn-link">Open {{ strtolower($subjectType?->label() ?? 'product') }}</a>@endif
        </x-slot:actions>
    </x-admin.page-header>

    @if (! $suggestion->subject)
        <p class="mb-4 rounded-control bg-error/10 px-4 py-3 text-sm text-error">The product of this suggestion no longer exists.</p>
    @endif
    @if (! empty($payload['notes']))
        <p class="mb-4 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700"><span class="font-semibold">Claude's note:</span> {{ $payload['notes'] }}</p>
    @endif

    <form id="seo-approve-form" method="POST" action="{{ route('admin.ai-suggestions.approve-seo', $suggestion->id) }}" novalidate>
        @csrf
        <div data-tabs>
            <div role="tablist" aria-label="Languages" class="mb-4 flex overflow-x-auto border-b border-ink-300/60">
                @foreach ($locales as $locale => $language)
                    <button type="button" role="tab" id="seo-{{ $locale }}-tab" aria-controls="seo-{{ $locale }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="tab-link">
                        {{ strtoupper($locale) }} · {{ $changes[$locale] }} {{ str('change')->plural($changes[$locale]) }}
                    </button>
                @endforeach
            </div>

            @foreach ($locales as $locale => $language)
                <div role="tabpanel" id="seo-{{ $locale }}" aria-labelledby="seo-{{ $locale }}-tab" @unless ($loop->first) hidden @endunless>
                    <x-admin.card :title="'Current vs suggested ('.$language.')'" :subtitle="$pending ? 'Suggested values are editable. Empty fields keep the current value.' : 'Values applied or proposed when the suggestion was decided.'">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs tracking-wide text-ink-500 uppercase">
                                        <th scope="col" class="w-44 py-2 pr-4">Field</th>
                                        <th scope="col" class="py-2 pr-4">Current</th>
                                        <th scope="col" class="py-2">Suggested{{ $pending ? ' (editable)' : '' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100">
                                    @foreach ($labels as $field => $label)
                                        @php
                                            $name = "locales[{$locale}][{$field}]";
                                            $key = "locales.{$locale}.{$field}";
                                            $inputId = "seo-{$locale}-{$field}";
                                            $suggested = old($key, $text($payload['locales'][$locale][$field] ?? null));
                                            $currentValue = $text($current['locales'][$locale][$field] ?? null);
                                            $changed = filled($payload['locales'][$locale][$field] ?? null) && $text($payload['locales'][$locale][$field]) !== $currentValue;
                                        @endphp
                                        <tr class="align-top">
                                            <th scope="row" class="py-3 pr-4 text-left font-semibold text-ink-900">
                                                <label for="{{ $inputId }}">{{ $label }}</label>
                                                @if ($changed)<span class="chip mt-1 block w-fit bg-warning/10 text-[#8a5f00]">Changed</span>@endif
                                            </th>
                                            <td class="py-3 pr-4"><div class="rounded-control bg-ink-100/70 px-3 py-2 text-ink-700">{{ $currentValue !== '' ? $currentValue : '—' }}</div></td>
                                            <td class="py-3">
                                                @if (in_array($field, ['meta_description', 'og_description'], true))
                                                    <textarea id="{{ $inputId }}" name="{{ $name }}" rows="3" maxlength="{{ $limits[$field] }}" @disabled(! $pending) @class(['form-control', 'is-invalid' => $errors->has($key)])>{{ $suggested }}</textarea>
                                                @else
                                                    <input id="{{ $inputId }}" name="{{ $name }}" type="text" value="{{ $suggested }}" @if (isset($limits[$field])) maxlength="{{ $limits[$field] }}" @endif @disabled(! $pending) @class(['form-control', 'is-invalid' => $errors->has($key) || $errors->has($key.'.*')])>
                                                @endif
                                                <div class="mt-1 flex justify-between gap-2 text-xs text-ink-500">
                                                    <span>@if ($field === 'keywords') Comma separated, max 20. @endif @error($key)<span class="text-error">{{ $message }}</span>@enderror @error($key.'.*')<span class="text-error">{{ $message }}</span>@enderror</span>
                                                    @isset($limits[$field])<span data-char-count-for="{{ $inputId }}" class="tabular-nums"></span>@endisset
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-admin.card>
                </div>
            @endforeach
        </div>

        @if ($subjectType?->hasSlug())
            <x-admin.card title="URL slug" class="mt-4" :subtitle="'Current: '.($current['slug'] ?? '—')">
                <div class="grid gap-3 md:grid-cols-6">
                    <x-admin.input name="slug" label="Suggested slug" :value="$payload['slug'] ?? null" span="md:col-span-4" :disabled="! $pending"
                                   :hint="$slugChanged ? 'Changing the slug changes the public URL: links to the old URL stop working.' : 'Leave empty to keep the current slug.'" />
                </div>
            </x-admin.card>
        @endif
    </form>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @if ($pending)
            <x-admin.card title="Approve" :subtitle="'Applies the edited values to the '.strtolower($subjectType?->label() ?? 'product').' in one transaction (EN and IT) and logs your decision.'">
                @error('suggestion')<p class="form-error mb-3">{{ $message }}</p>@enderror
                @error('slug')<p class="form-error mb-3">{{ $message }}</p>@enderror
                <div class="flex justify-end">
                    <button type="submit" form="seo-approve-form" class="btn-cta" @disabled(! $suggestion->subject)>Approve and apply</button>
                </div>
            </x-admin.card>
            <x-admin.card title="Reject">
                @include('admin.ai-suggestions.partials.reject')
            </x-admin.card>
        @else
            @include('admin.ai-suggestions.partials.decided')
        @endif
    </div>
</x-admin.layout>
