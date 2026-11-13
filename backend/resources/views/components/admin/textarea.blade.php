@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'rows' => 4, 'id' => null, 'span' => 'md:col-span-6'])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $error = $errors->first($key);
@endphp
<div data-field @class([$span])>
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($error) aria-invalid="true" @endif {{ $attributes->class(['form-control', 'is-invalid' => $error]) }}>{{ old($key, $value) }}</textarea>
    @if ($error)
        <p class="form-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
