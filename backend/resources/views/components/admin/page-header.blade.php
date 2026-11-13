@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($breadcrumbs !== [])
            <nav aria-label="Breadcrumb" class="mb-2">
                <ol class="flex flex-wrap items-center gap-1.5 text-xs text-ink-700">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1.5">
                            @if (! $loop->first)<span class="text-ink-300">/</span>@endif
                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-ink-900 hover:underline">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="truncate text-[28px] leading-tight text-ink-900 sm:text-[32px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-ink-700">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
