{{-- EN / IT tabs for a translatable JSON column. $value is the {locale: text} map. --}}
@props(['name', 'label', 'value' => [], 'type' => 'input', 'rows' => 4, 'span' => 'md:col-span-6', 'locales' => ['en' => 'English', 'it' => 'Italiano'], 'requiredLocale' => null])
@php
    $baseId = 'tr-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = collect(array_keys($locales))->contains(fn ($locale) => $errors->has($key.'.'.$locale)) || $errors->has($key);
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
            @if ($type === 'textarea')
                <textarea name="{{ $name }}[{{ $locale }}]" rows="{{ $rows }}" lang="{{ $locale }}" aria-label="{{ $label }} ({{ $localeName }})" @class(['form-control', 'is-invalid' => $fieldError])>{{ old($key.'.'.$locale, $value[$locale] ?? '') }}</textarea>
            @else
                <input type="text" name="{{ $name }}[{{ $locale }}]" value="{{ old($key.'.'.$locale, $value[$locale] ?? '') }}" lang="{{ $locale }}" aria-label="{{ $label }} ({{ $localeName }})" @class(['form-control', 'is-invalid' => $fieldError])>
            @endif
            @if ($fieldError)<p class="form-error">{{ $fieldError }}</p>@endif
        </div>
    @endforeach
    @if ($errors->has($key))<p class="form-error">{{ $errors->first($key) }}</p>@endif
</div>
