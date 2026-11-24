@php
    use App\Enums\PublishStatus;
    use App\Services\ContentService;
    $title = $post->exists ? (string) $post->translate('title', 'en') : 'New post';
    $tags = collect($post->getTranslations('tags'))->map(fn ($list) => implode(', ', (array) $list))->all();
    $cover = ContentService::coverUrl($post->cover_image);
    $coverPreview = $cover && str_starts_with($cover, '/') ? rtrim(config('app.frontend_url', ''), '/').$cover : $cover;
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Content' => route('admin.content.index'), 'Blog' => route('admin.content.index').'#tab-blog', $title => null]"
                         :subtitle="$post->exists ? '/blog/'.$post->slug.' · '.$post->reading_minutes.' min read' : null">
        <x-slot:actions>
            @if ($post->exists)<x-admin.status-chip :status="$post->status" />@endif
            <button type="submit" form="post-form" class="btn-cta">Save</button>
        </x-slot:actions>
    </x-admin.page-header>
    <form id="post-form" method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.content.posts.update', $post) : route('admin.content.posts.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($post->exists) @method('PUT') @endif
        <x-admin.form-section title="Article">
            <x-admin.translatable name="title" label="Title" :value="$post->getTranslations('title')" required-locale="en" />
            <x-admin.translatable name="excerpt" label="Excerpt" type="textarea" :rows="2" :value="$post->getTranslations('excerpt')" />
            <x-admin.rich-text name="body" label="Body" :value="$post->getTranslations('body')" :rows="16" hint="Reading time is computed from the longest language version when you save." />
        </x-admin.form-section>
        <x-admin.form-section title="Publishing">
            <x-admin.input name="slug" label="Address" :value="$post->slug" prefix="/blog/" class="pl-16" hint="Empty = from the English title" span="md:col-span-3" />
            <x-admin.input name="author" label="Author" :value="$post->author" maxlength="120" span="md:col-span-3" />
            <x-admin.select name="status" label="Status" :options="PublishStatus::options()" :selected="$post->status" :select2="false" span="md:col-span-2" />
            <x-admin.input name="published_at" label="Publish date" data-datepicker :value="$post->published_at?->toDateString()" placeholder="Today when published" span="md:col-span-2" />
            <div class="md:col-span-2"></div>
            <x-admin.input name="tags[en]" label="Tags (English)" :value="$tags['en'] ?? null" hint="Comma separated" span="md:col-span-3" />
            <x-admin.input name="tags[it]" label="Tags (Italiano)" :value="$tags['it'] ?? null" hint="Separati da virgola" span="md:col-span-3" />
            <div data-field class="md:col-span-6">
                <label for="f-cover" class="form-label">Cover image</label>
                <div class="flex flex-wrap items-start gap-4">
                    @if ($coverPreview)
                        <img src="{{ $coverPreview }}" alt="" class="h-24 w-40 rounded-image border border-ink-100 object-cover">
                    @endif
                    <div class="min-w-0 flex-1">
                        <input id="f-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" @class(['form-control', 'is-invalid' => $errors->has('cover')])>
                        @error('cover')<p class="form-error">{{ $message }}</p>@else<p class="form-hint">JPG, PNG or WebP, at least 600 px wide, max 4 MB.{{ $post->cover_image ? ' Current: '.$post->cover_image : '' }}</p>@enderror
                        @if ($post->cover_image)
                            <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> Remove the cover</label>
                        @endif
                    </div>
                </div>
            </div>
        </x-admin.form-section>
        @include('admin.content.partials.seo', ['seo' => $post->getTranslations('seo')])
    </form>
    @if ($post->exists)
        <form method="POST" action="{{ route('admin.content.posts.destroy', $post) }}" class="mt-4 flex justify-end" data-confirm="Delete this post?">
            @csrf @method('DELETE')<button type="submit" class="btn-danger">Delete post</button>
        </form>
    @endif
</x-admin.layout>
