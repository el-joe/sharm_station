<x-admin.layout :title="(string) $category->translate('title', 'en')">
    <x-admin.page-header :title="(string) $category->translate('title', 'en')" :breadcrumbs="['Content' => route('admin.content.index'), 'FAQ' => route('admin.content.index').'#tab-faq', 'Category' => null]">
        <x-slot:actions><button type="submit" form="faq-category-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="faq-category-form" method="POST" action="{{ route('admin.content.faq.categories.update', $category) }}" novalidate>
        @csrf @method('PUT')
        <x-admin.form-section title="FAQ category">
            <x-admin.translatable name="title" label="Title" :value="$category->getTranslations('title')" required-locale="en" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
