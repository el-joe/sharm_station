<?php

namespace App\Repositories\Eloquent;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Repositories\Contracts\FaqRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<FaqCategory>
 */
class FaqRepository extends BaseRepository implements FaqRepositoryInterface
{
    protected function model(): string
    {
        return FaqCategory::class;
    }

    public function categoriesWithItems(bool $activeOnly = false): Collection
    {
        return $this->query()
            ->with(['items' => fn (HasMany $items) => $items->when($activeOnly, fn ($query) => $query->where('is_active', true))])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function createItem(FaqCategory $category, array $attributes): FaqItem
    {
        return $category->items()->create($attributes);
    }

    public function updateItem(FaqItem $item, array $attributes): FaqItem
    {
        $item->fill($attributes)->save();

        return $item;
    }

    public function deleteItem(FaqItem $item): void
    {
        $item->delete();
    }

    public function nextCategorySort(): int
    {
        return (int) $this->query()->max('sort') + 1;
    }

    public function nextItemSort(FaqCategory $category): int
    {
        return (int) $category->items()->max('sort') + 1;
    }

    public function reorderCategories(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                $this->query()->whereKey($id)->update(['sort' => $position]);
            }
        });
    }

    public function reorderItems(FaqCategory $category, array $ids): void
    {
        DB::transaction(function () use ($category, $ids): void {
            foreach (array_values($ids) as $position => $id) {
                FaqItem::query()->whereKey($id)->where('faq_category_id', $category->id)->update(['sort' => $position]);
            }
        });
    }
}
