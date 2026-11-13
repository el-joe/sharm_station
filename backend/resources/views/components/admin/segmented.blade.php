{{-- Segmented radio control. $options: value => [label, description]. --}}
@props(['name', 'options', 'selected' => null, 'label' => null, 'span' => 'md:col-span-6'])
@php($current = (string) old($name, $selected instanceof \BackedEnum ? $selected->value : $selected))
<fieldset data-field @class([$span])>
    @if ($label)<legend class="form-label">{{ $label }}</legend>@endif
    <div class="inline-grid w-full grid-cols-1 gap-1 rounded-card bg-ink-100 p-1 sm:w-auto {{ count($options) === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
        @foreach ($options as $value => [$text, $description])
            <label class="cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" class="peer sr-only" @checked($current === (string) $value) {{ $attributes }}>
                <span class="block rounded-[12px] border-2 border-transparent px-4 py-2.5 peer-checked:border-primary-700 peer-checked:bg-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">
                    <span class="block text-sm font-semibold text-ink-900">{{ $text }}</span>
                    <span class="block text-xs text-ink-500">{{ $description }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</fieldset>
