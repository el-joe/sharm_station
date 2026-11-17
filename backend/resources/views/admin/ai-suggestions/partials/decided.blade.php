{{-- Read-only decision card of an approved or rejected suggestion. --}}
<x-admin.card title="Decision">
    <div class="flex items-center gap-2 text-sm">
        <x-admin.status-chip :status="$suggestion->status" />
        <span>by {{ $suggestion->reviewer?->name ?? '—' }} on {{ $suggestion->reviewed_at?->format('j M Y, H:i') ?? '—' }}</span>
    </div>
    @if ($suggestion->review_note)
        <p class="mt-3 rounded-control bg-error/10 px-4 py-3 text-sm text-error">{{ $suggestion->review_note }}</p>
    @endif
    <p class="mt-3 text-xs text-ink-500">Decided suggestions are read-only.</p>
</x-admin.card>
