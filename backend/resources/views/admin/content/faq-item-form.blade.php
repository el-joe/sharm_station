@php $title = $item->exists ? (string) $item->translate('question', 'en') : 'New question'; @endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Content' => route('admin.content.index'), 'FAQ' => route('admin.content.index').'#tab-faq', $item->exists ? 'Question' : 'New question' => null]">
        <x-slot:actions><button type="submit" form="faq-item-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="faq-item-form" method="POST" action="{{ $item->exists ? route('admin.content.faq.items.update', $item) : route('admin.content.faq.items.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <x-admin.form-section title="Question">
            <x-admin.select name="faq_category_id" label="Category" :options="$categories" :selected="$item->faq_category_id" placeholder="Choose a category" span="md:col-span-3" />
            <x-admin.switch name="is_active" label="Visible on the website" :checked="$item->is_active" />
            <x-admin.translatable name="question" label="Question" :value="$item->getTranslations('question')" required-locale="en" />
            <x-admin.rich-text name="answer" label="Answer" :value="$item->getTranslations('answer')" :rows="6" required-locale="en" />
        </x-admin.form-section>
    </form>
    @if ($item->exists)
        <form method="POST" action="{{ route('admin.content.faq.items.destroy', $item) }}" class="mt-4 flex justify-end" data-confirm="Delete this question?">
            @csrf @method('DELETE')<button type="submit" class="btn-danger">Delete question</button>
        </form>
    @endif
</x-admin.layout>
