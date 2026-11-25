<nav aria-label="Notifications sections" class="mb-5 flex overflow-x-auto border-b border-ink-300/60">
    <a href="{{ route('admin.notifications.index') }}" class="tab-link" @if ($active === 'templates') aria-selected="true" aria-current="page" @endif>Templates</a>
    <a href="{{ route('admin.notifications.log') }}" class="tab-link" @if ($active === 'log') aria-selected="true" aria-current="page" @endif>Sending log</a>
</nav>
