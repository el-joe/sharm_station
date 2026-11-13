<x-admin.layout :title="'Message from '.$message->name">
    <x-admin.page-header :title="'Message from '.$message->name" :breadcrumbs="['Sales' => null, 'Contact messages' => route('admin.contact-messages.index'), $message->name => null]"
                         :subtitle="$message->subject->label().' · '.$message->created_at->format('j M Y H:i').' · '.strtoupper($message->locale)" />
    <x-admin.card>
        <dl class="mb-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-ink-500">Email</dt><dd><a href="mailto:{{ $message->email }}" class="font-medium hover:underline">{{ $message->email }}</a></dd></div>
            <div><dt class="text-ink-500">Phone</dt><dd class="font-medium">{{ $message->phone ?? '—' }}</dd></div>
            <div><dt class="text-ink-500">Booking</dt><dd class="font-medium">{{ $message->booking_reference ?? '—' }}</dd></div>
        </dl>
        <p class="whitespace-pre-line text-sm text-ink-900">{{ $message->message }}</p>
    </x-admin.card>
</x-admin.layout>
