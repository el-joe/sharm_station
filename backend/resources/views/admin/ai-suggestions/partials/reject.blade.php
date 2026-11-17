{{-- Reject form (reason required). Nothing is changed on the website. --}}
<form method="POST" action="{{ route('admin.ai-suggestions.reject', $suggestion->id) }}" class="space-y-3" novalidate>
    @csrf
    <x-admin.textarea name="review_note" label="Rejection reason (required)" :rows="3" span="" placeholder="e.g. Keywords too generic; keep the current slug." />
    <div class="flex justify-end"><button type="submit" class="btn-danger">Reject suggestion</button></div>
</form>
