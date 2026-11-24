@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'prefix' => null,
    'suffix' => null,
    'id' => null,
    'span' => 'md:col-span-3',
    'labelHidden' => false,
])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $current = $type === 'password' ? null : old($key, $value);
    $error = $errors->first($key);
@endphp
<div data-field @class([$span])>
    @if ($label)<label for="{{ $id }}" @class(['form-label', 'sr-only' => $labelHidden])>{{ $label }}@if ($attributes->has('required'))<span class="text-error"> *</span>@endif</label>@endif
    <div class="relative">
        @if ($prefix)<span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-ink-500">{{ $prefix }}</span>@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if (! is_null($current)) value="{{ $current }}" @endif
               @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
               {{ $attributes->class(['form-control', 'pl-8' => $prefix, 'pr-9' => $suffix, 'is-invalid' => $error]) }}>
        @if ($suffix)<span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">{{ $suffix }}</span>@endif
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="form-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="form-hint">{{ $hint }}</p>
    @endif
</div>
