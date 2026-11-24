{{-- EN / IT tabs with a small HTML editor (toolbar + live preview) per language. The HTML is sanitized on save. --}}
@props(['name', 'label', 'value' => [], 'rows' => 12, 'span' => 'md:col-span-6', 'locales' => ['en' => 'English', 'it' => 'Italiano'], 'requiredLocale' => null, 'hint' => null])
@php
    $baseId = 'rt-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $tools = [
        ['h2', 'H2', 'Heading'], ['h3', 'H3', 'Subheading'], ['p', '¶', 'Paragraph'], ['strong', 'B', 'Bold'], ['em', 'I', 'Italic'],
        ['ul', '• List', 'Bulleted list'], ['ol', '1. List', 'Numbered list'], ['blockquote', '“ ”', 'Quote'], ['a', 'Link', 'Link'],
    ];
@endphp
<div data-field data-tabs @class([$span])>
    <div class="mb-1.5 flex items-center justify-between gap-3">
        <span class="form-label mb-0">{{ $label }}@if ($requiredLocale)<span class="text-error"> *</span>@endif</span>
        <div role="tablist" aria-label="{{ $label }} language" class="inline-flex rounded-control bg-ink-100 p-0.5 text-xs font-semibold">
            @foreach ($locales as $locale => $localeName)
                <button type="button" role="tab" id="{{ $baseId }}-{{ $locale }}-tab" aria-controls="{{ $baseId }}-{{ $locale }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        class="rounded-[8px] px-2.5 py-1 text-ink-500 aria-selected:bg-white aria-selected:text-ink-900 aria-selected:shadow-sm" title="{{ $localeName }}">
                    {{ strtoupper($locale) }}@if ($errors->has($key.'.'.$locale))<span class="text-error">•</span>@endif
                </button>
            @endforeach
        </div>
    </div>
    @foreach ($locales as $locale => $localeName)
        @php($fieldError = $errors->first($key.'.'.$locale))
        <div role="tabpanel" id="{{ $baseId }}-{{ $locale }}" aria-labelledby="{{ $baseId }}-{{ $locale }}-tab" @if (! $loop->first) hidden @endif>
            <div data-rich-editor class="grid gap-3 lg:grid-cols-2">
                <div class="min-w-0">
                    <div role="toolbar" aria-label="Formatting" class="mb-1.5 flex flex-wrap gap-1">
                        @foreach ($tools as [$command, $text, $title])
                            <button type="button" data-cmd="{{ $command }}" title="{{ $title }}" aria-label="{{ $title }}" class="rounded-[8px] border border-ink-300 bg-white px-2 py-1 text-xs font-semibold text-ink-700 hover:border-primary-500 hover:text-primary-700">{{ $text }}</button>
                        @endforeach
                    </div>
                    <textarea name="{{ $name }}[{{ $locale }}]" rows="{{ $rows }}" lang="{{ $locale }}" spellcheck="true" aria-label="{{ $label }} ({{ $localeName }}, HTML)" data-rich-source
                              @class(['form-control font-mono text-[13px] leading-relaxed', 'is-invalid' => $fieldError])>{{ old($key.'.'.$locale, $value[$locale] ?? '') }}</textarea>
                    @if ($fieldError)<p class="form-error">{{ $fieldError }}</p>@elseif ($hint && $loop->first)<p class="form-hint">{{ $hint }}</p>@endif
                </div>
                <div class="min-w-0">
                    <p class="mb-1.5 text-xs font-semibold tracking-wide text-ink-500 uppercase">Preview</p>
                    <div data-rich-preview class="rich-preview max-h-[480px] min-h-32 overflow-y-auto rounded-control border border-ink-100 bg-white p-4 text-sm" lang="{{ $locale }}"></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
