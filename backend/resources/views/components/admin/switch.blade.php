@props(['name', 'label', 'checked' => false, 'description' => null, 'id' => null, 'span' => 'md:col-span-3'])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $isOn = (bool) old($key, $checked);
@endphp
<div data-field @class([$span, 'flex items-start gap-3'])>
    <input type="hidden" name="{{ $name }}" value="0">
    <label for="{{ $id }}" class="relative mt-0.5 inline-flex shrink-0 cursor-pointer items-center">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="1" @checked($isOn) {{ $attributes->class(['peer sr-only']) }}>
        <span class="h-6 w-11 rounded-pill bg-ink-300 transition peer-checked:bg-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-disabled:opacity-50"></span>
        <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </label>
    <div class="min-w-0">
        <label for="{{ $id }}" class="block cursor-pointer text-sm font-medium text-ink-900">{{ $label }}</label>
        @if ($description)<p class="text-xs text-ink-500">{{ $description }}</p>@endif
        @error($key)<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>
