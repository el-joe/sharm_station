@php use App\Enums\PublishStatus; @endphp
<x-admin.layout title="Content">
    <x-admin.page-header title="Content" :breadcrumbs="['Content' => null, 'Pages, blog and FAQ' => null]" subtitle="Texts of the website in English and Italian. HTML is cleaned of unsafe tags when you save.">
        <x-slot:actions>
            <a href="{{ route('admin.content.posts.create') }}" class="btn-primary"><x-admin.icon name="plus" size="18" /> New post</a>
            <a href="{{ route('admin.content.faq.items.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New question</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div data-tabs data-tabs-hash>
        <div role="tablist" aria-label="Content sections" class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-ink-300/60">
            <button type="button" role="tab" id="tab-pages-tab" aria-controls="tab-pages" aria-selected="true" class="tab-link">Pages</button>
            <button type="button" role="tab" id="tab-blog-tab" aria-controls="tab-blog" aria-selected="false" class="tab-link">Blog</button>
            <button type="button" role="tab" id="tab-faq-tab" aria-controls="tab-faq" aria-selected="false" class="tab-link">FAQ</button>
        </div>

        <div role="tabpanel" id="tab-pages" aria-labelledby="tab-pages-tab">
            <x-admin.card :padding="false" title="Static pages" subtitle="Fixed pages linked from the footer and the checkout.">
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[560px]">
                        <thead><tr><th scope="col">Page</th><th scope="col">Address</th><th scope="col">Languages</th><th scope="col">Last update</th><th scope="col"></th></tr></thead>
                        <tbody>
                            @foreach ($pages as ['slug' => $slug, 'page' => $page])
                                <tr>
                                    <td class="font-semibold"><a href="{{ route('admin.content.pages.edit', $slug) }}" class="hover:underline">{{ $page?->translate('title', 'en') ?? $slug->label() }}</a>
                                        <div class="text-xs font-normal text-ink-500">{{ $slug->label() }}</div></td>
                                    <td class="text-ink-700">/{{ $slug->value }}</td>
                                    <td>{{ $page ? strtoupper(implode(' · ', array_keys($page->getTranslations('title')))) : '—' }}</td>
                                    <td>{{ $page?->updated_at?->format('j M Y') ?? 'Not written yet' }}</td>
                                    <td class="text-right"><a href="{{ route('admin.content.pages.edit', $slug) }}" class="btn-link">Edit</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div role="tabpanel" id="tab-blog" aria-labelledby="tab-blog-tab" hidden>
            <form id="blog-filters" class="card mb-4 flex flex-wrap items-end gap-3 p-4" aria-label="Blog filters">
                <x-admin.select name="status" label="Status" :options="PublishStatus::options()" placeholder="Any status" data-s2-clear span="w-full sm:w-56" />
            </form>
            <x-admin.datatable id="blog-table" :url="route('admin.content.blog.data')" filters="#blog-filters" :order="[[2, 'desc']]" search-placeholder="Search posts" empty="No posts yet." :columns="[
                ['data' => 'title', 'name' => 'title', 'title' => 'Title'],
                ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
                ['data' => 'published_at', 'name' => 'published_at', 'title' => 'Published', 'searchable' => false],
                ['data' => 'author', 'name' => 'author', 'title' => 'Author'],
                ['data' => 'reading_minutes', 'name' => 'reading_minutes', 'title' => 'Min', 'searchable' => false],
                ['data' => 'languages', 'name' => 'languages', 'title' => 'Languages', 'searchable' => false, 'orderable' => false],
                ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ]" />
        </div>

        <div role="tabpanel" id="tab-faq" aria-labelledby="tab-faq-tab" hidden class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div data-sortable-save="{{ route('admin.content.faq.reorder') }}">
                    <p class="mb-3 text-sm text-ink-700">Drag the handles to reorder categories and questions; the order is saved immediately.</p>
                    <ul data-sortable-list="categories" class="space-y-4">
                        @forelse ($categories as $category)
                            <li data-sortable-id="{{ $category->id }}" class="card p-4 sm:p-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" data-drag-handle class="cursor-grab text-ink-500 hover:text-ink-900" aria-label="Drag to reorder {{ $category->title }}"><x-admin.icon name="grip" size="18" /></button>
                                    <h2 class="flex-1 text-lg">{{ $category->translate('title', 'en') }} <span class="text-sm font-normal text-ink-500">/ {{ $category->translate('title', 'it') }}</span></h2>
                                    <a href="{{ route('admin.content.faq.items.create', ['category' => $category->id]) }}" class="btn-link">Add question</a>
                                    <a href="{{ route('admin.content.faq.categories.edit', $category) }}" class="btn-link">Rename</a>
                                    <form method="POST" action="{{ route('admin.content.faq.categories.destroy', $category) }}" data-confirm="Delete the category “{{ $category->translate('title', 'en') }}” and its {{ $category->items->count() }} questions?">
                                        @csrf @method('DELETE')<button type="submit" class="text-sm font-semibold text-error hover:underline">Delete</button>
                                    </form>
                                </div>
                                <ul data-sortable-list="items" data-parent-id="{{ $category->id }}" class="mt-3 divide-y divide-ink-100 border-t border-ink-100">
                                    @forelse ($category->items as $item)
                                        <li data-sortable-id="{{ $item->id }}" class="flex items-start gap-3 py-2.5">
                                            <button type="button" data-drag-handle class="mt-0.5 cursor-grab text-ink-300 hover:text-ink-900" aria-label="Drag to reorder"><x-admin.icon name="grip" size="16" /></button>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('admin.content.faq.items.edit', $item) }}" class="text-sm font-medium text-ink-900 hover:underline">{{ $item->translate('question', 'en') }}</a>
                                                <p class="truncate text-xs text-ink-500">{{ $item->translate('question', 'it') }}</p>
                                            </div>
                                            @unless ($item->is_active)<x-admin.status-chip status="hidden" />@endunless
                                            <a href="{{ route('admin.content.faq.items.edit', $item) }}" class="btn-link text-sm">Edit</a>
                                        </li>
                                    @empty
                                        <li class="py-3 text-sm text-ink-500">No questions in this category.</li>
                                    @endforelse
                                </ul>
                            </li>
                        @empty
                            <li class="card p-6 text-center text-sm text-ink-500">No FAQ categories yet.</li>
                        @endforelse
                    </ul>
                </div>
                <x-admin.card title="New category">
                    <form method="POST" action="{{ route('admin.content.faq.categories.store') }}" class="grid gap-4" novalidate>
                        @csrf
                        <x-admin.translatable name="title" label="Title" required-locale="en" span="" />
                        <button type="submit" class="btn-primary">Add category</button>
                    </form>
                </x-admin.card>
            </div>
        </div>
    </div>
</x-admin.layout>
