<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Content\FaqCategoryData;
use App\DTOs\Content\FaqItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqCategoryRequest;
use App\Http\Requests\Admin\FaqItemRequest;
use App\Http\Requests\Admin\FaqReorderRequest;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Services\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FAQ categories and entries (translatable, drag to reorder).
 */
class FaqController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function storeCategory(FaqCategoryRequest $request): RedirectResponse
    {
        $this->content->createFaqCategory(FaqCategoryData::fromRequest($request));

        return $this->backToFaq('Category added.');
    }

    public function editCategory(FaqCategory $category): View
    {
        return view('admin.content.faq-category-form', ['category' => $category]);
    }

    public function updateCategory(FaqCategoryRequest $request, FaqCategory $category): RedirectResponse
    {
        $this->content->updateFaqCategory($category, FaqCategoryData::fromRequest($request));

        return $this->backToFaq('Category saved.');
    }

    public function destroyCategory(FaqCategory $category): RedirectResponse
    {
        $this->content->deleteFaqCategory($category);

        return $this->backToFaq('Category and its questions deleted.');
    }

    public function createItem(Request $request): View
    {
        return view('admin.content.faq-item-form', [
            'item' => new FaqItem(['is_active' => true, 'faq_category_id' => (int) $request->query('category')]),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function storeItem(FaqItemRequest $request): RedirectResponse
    {
        $this->content->createFaqItem(FaqItemData::fromRequest($request));

        return $this->backToFaq('Question added.');
    }

    public function editItem(FaqItem $item): View
    {
        return view('admin.content.faq-item-form', ['item' => $item, 'categories' => $this->categoryOptions()]);
    }

    public function updateItem(FaqItemRequest $request, FaqItem $item): RedirectResponse
    {
        $this->content->updateFaqItem($item, FaqItemData::fromRequest($request));

        return $this->backToFaq('Question saved.');
    }

    public function destroyItem(FaqItem $item): RedirectResponse
    {
        $this->content->deleteFaqItem($item);

        return $this->backToFaq('Question deleted.');
    }

    public function reorder(FaqReorderRequest $request): JsonResponse
    {
        $this->content->reorderFaq($request->categoryIds(), $request->itemIds());

        return response()->json(['message' => 'Order saved.']);
    }

    /**
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        return $this->content->faqCategories()->mapWithKeys(fn (FaqCategory $category): array => [$category->id => (string) $category->title])->all();
    }

    private function backToFaq(string $message): RedirectResponse
    {
        return redirect()->to(route('admin.content.index').'#tab-faq')->with('success', $message);
    }
}
