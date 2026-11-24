<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BlogPostDataTable;
use App\DTOs\Content\PageData;
use App\Enums\PageSlug;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Services\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Content hub (SRS 14.1): tabs Pages / Blog / FAQ, and the fixed static pages editor.
 */
class ContentController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function index(): View
    {
        return view('admin.content.index', [
            'pages' => $this->content->pageList(),
            'categories' => $this->content->faqCategories(),
        ]);
    }

    public function blogData(Request $request, BlogPostDataTable $table): JsonResponse
    {
        return $table->json(PublishStatus::tryFrom((string) $request->query('status')));
    }

    public function editPage(PageSlug $slug): View
    {
        return view('admin.content.page-form', ['slug' => $slug, 'page' => $this->content->pageForEditing($slug)]);
    }

    public function updatePage(PageRequest $request, PageSlug $slug): RedirectResponse
    {
        $this->content->savePage($slug, PageData::fromRequest($request));

        return redirect()->route('admin.content.pages.edit', $slug)->with('success', "“{$slug->label()}” saved.");
    }
}
