@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->class(['card', 'p-5 sm:p-6' => $padding]) }}>
    @if ($title || isset($actions))
        <div @class(['mb-5 flex flex-wrap items-start justify-between gap-3', 'px-5 pt-5 sm:px-6 sm:pt-6' => ! $padding])>
            <div>
                @if ($title)<h2 class="font-sans text-lg font-semibold text-ink-900" style="font-family: var(--font-sans)">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="mt-0.5 text-sm text-ink-700">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
</section>
