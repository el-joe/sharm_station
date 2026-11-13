@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'multiple' => false,
    'select2' => true,
    'tags' => false,
    'id' => null,
    'span' => 'md:col-span-3',
])
@php
    $key = str_replace(['[', ']'], ['.', ''], preg_replace('/\[\]$/', '', $name));
    $id ??= 'f-'.trim(str_replace(['[', ']', '.'], ['-', '', '-'], $name), '-');
    $current = old($key, $selected instanceof \BackedEnum ? $selected->value : $selected);
    $current = collect(is_iterable($current) ? $current : [$current])->map(fn ($value) => $value instanceof \BackedEnum ? (string) $value->value : (string) $value)->all();
    $error = $errors->first($key) ?: $errors->first($key.'.*');
    // Tag selects keep values that are not part of the predefined options.
    if ($tags) {
        foreach ($current as $value) {
            if ($value !== '' && ! array_key_exists($value, $options)) {
                $options[$value] = $value;
            }
        }
    }
@endphp
<div data-field @class([$span])>
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($attributes->has('required'))<span class="text-error"> *</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($multiple) multiple @endif
            @if ($select2) data-s2 @endif @if ($tags) data-s2-tags @endif
            @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $error]) }}>
        @if ($placeholder && ! $multiple)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $current, true))>{{ $text }}</option>
        @endforeach
    </select>
    @if ($error)
        <p class="form-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
