@props(['label', 'value', 'icon' => 'calendar', 'hint' => null])
<div class="card p-5">
    <div class="flex items-center gap-3">
        <span class="grid size-11 place-items-center rounded-full bg-primary-100 text-primary-700"><x-admin.icon :name="$icon" /></span>
        <p class="text-sm text-ink-700">{{ $label }}</p>
    </div>
    <p class="mt-4 font-sans text-3xl font-bold tracking-tight text-ink-900">{{ $value }}</p>
    @if ($hint)<p class="mt-2 text-xs text-ink-500">{{ $hint }}</p>@endif
</div>
