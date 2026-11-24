<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Content\BlogPostData;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogPost;
use App\Services\ContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function create(): View
    {
        return view('admin.content.post-form', ['post' => new BlogPost(['status' => PublishStatus::Draft, 'author' => auth()->user()?->name])]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $post = $this->content->createPost(BlogPostData::fromRequest($request));

        return redirect()->route('admin.content.posts.edit', $post)->with('success', 'Post created.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.content.post-form', ['post' => $post]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->content->updatePost($post, BlogPostData::fromRequest($request));

        return redirect()->route('admin.content.posts.edit', $post)->with('success', 'Post saved.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->content->deletePost($post);

        return redirect()->to(route('admin.content.index').'#tab-blog')->with('success', 'Post deleted.');
    }
}
