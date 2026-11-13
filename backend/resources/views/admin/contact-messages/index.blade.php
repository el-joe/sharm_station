<x-admin.layout title="Contact messages">
    <x-admin.page-header title="Contact messages" :breadcrumbs="['Sales' => null, 'Contact messages' => null]" subtitle="Sent from the contact form on the site." />
    <x-admin.datatable id="contact-messages-table" :url="route('admin.contact-messages.data')" :order="[[4, 'desc']]" search-placeholder="Name or email" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'From'],
        ['data' => 'subject', 'name' => 'subject', 'title' => 'Subject', 'searchable' => false],
        ['data' => 'message', 'name' => 'message', 'title' => 'Message', 'orderable' => false, 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Received', 'searchable' => false],
    ]" />
</x-admin.layout>
