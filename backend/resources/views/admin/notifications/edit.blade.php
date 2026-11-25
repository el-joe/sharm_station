<x-admin.layout :title="$event->label().' ('.strtoupper($locale).')'">
    <x-admin.page-header :title="$event->label()" :breadcrumbs="['Settings' => null, 'Notifications' => route('admin.notifications.index'), $event->label() => null, strtoupper($locale) => null]"
                         :subtitle="'To: '.$event->audience()->label().' · language '.strtoupper($locale).($template['stored'] ? '' : ' · default text, not saved yet')">
        <x-slot:actions>
            <a href="{{ route('admin.notifications.edit', [$event, $locale === 'en' ? 'it' : 'en']) }}" class="btn-ghost">Edit {{ $locale === 'en' ? 'Italian' : 'English' }}</a>
            <button type="submit" form="template-form" class="btn-cta">Save template</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <form id="template-form" method="POST" action="{{ route('admin.notifications.update', [$event, $locale]) }}" class="space-y-4" novalidate
              data-template-preview="{{ route('admin.notifications.preview', [$event, $locale]) }}" data-preview-frame="#template-preview" data-preview-subject="#template-preview-subject">
            @csrf @method('PUT')
            <x-admin.card title="Template">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <x-admin.switch name="is_enabled" label="Send this email" :checked="$template['enabled']" description="When off, nothing is sent for this event in this language." span="md:col-span-6" />
                    <x-admin.input name="subject" label="Subject" :value="$template['subject']" required maxlength="200" span="md:col-span-6" />
                    <x-admin.textarea name="body" label="Body" :value="$template['body']" :rows="14" hint="Plain text. Blank line = new paragraph, **text** = bold. Values are escaped automatically." span="md:col-span-6" class="font-mono text-sm" />
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="btn-ghost btn-sm" data-preview-refresh>Refresh preview</button>
                    <button type="submit" class="btn-ghost btn-sm" formaction="{{ route('admin.notifications.test', [$event, $locale]) }}">Send test to me ({{ auth()->user()->email }})</button>
                </div>
            </x-admin.card>
            <x-admin.card title="Placeholders" subtitle="Click to copy. Only these are allowed for this event.">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($event->placeholders() as $placeholder)
                        @php($token = str_repeat('{', 2).$placeholder.str_repeat('}', 2))
                        <li><button type="button" class="chip bg-ink-100 font-mono text-ink-700 hover:bg-primary-100" data-copy-text="{{ $token }}">{{ $token }}</button></li>
                    @endforeach
                </ul>
            </x-admin.card>
        </form>

        <x-admin.card title="Preview" subtitle="With sample values.">
            <p class="mb-3 text-sm"><span class="text-ink-500">Subject:</span> <strong id="template-preview-subject">{{ $template['subject'] }}</strong></p>
            <iframe id="template-preview" title="Email preview" class="h-[720px] w-full rounded-card border border-ink-100 bg-white" sandbox="" srcdoc="{{ $previewHtml }}"></iframe>
        </x-admin.card>
    </div>
</x-admin.layout>
