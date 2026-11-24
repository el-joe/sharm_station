@php $title = $page->exists ? (string) $page->translate('title', 'en') : $slug->label(); @endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Content' => route('admin.content.index'), 'Pages' => route('admin.content.index').'#tab-pages', $slug->label() => null]"
                         :subtitle="'/'.$slug->value.($page->updated_at ? ' · updated '.$page->updated_at->format('j M Y H:i') : '')">
        <x-slot:actions><button type="submit" form="page-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="page-form" method="POST" action="{{ route('admin.content.pages.update', $slug) }}" novalidate class="space-y-4">
        @csrf @method('PUT')
        <x-admin.form-section title="Page">
            <x-admin.translatable name="title" label="Title" :value="$page->getTranslations('title')" required-locale="en" />
            <x-admin.translatable name="intro" label="Introduction" type="textarea" :rows="3" :value="$page->getTranslations('intro')" />
            <x-admin.rich-text name="body" label="Body" :value="$page->getTranslations('body')" hint="Use H2 headings for the sections: they become the page's table of contents." />
        </x-admin.form-section>
        @include('admin.content.partials.seo', ['seo' => $page->getTranslations('seo')])
    </form>
</x-admin.layout>
