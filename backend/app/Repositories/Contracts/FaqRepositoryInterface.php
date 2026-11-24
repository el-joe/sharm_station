<?php

namespace App\Repositories\Contracts;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * FAQ categories (aggregate root) and their items.
 *
 * @extends BaseRepositoryInterface<FaqCategory>
 */
interface FaqRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Categories in order with their items (only active items when $activeOnly).
     *
     * @return Collection<int, FaqCategory>
     */
    public function categoriesWithItems(bool $activeOnly = false): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(FaqCategory $category, array $attributes): FaqItem;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(FaqItem $item, array $attributes): FaqItem;

    public function deleteItem(FaqItem $item): void;

    public function nextCategorySort(): int;

    public function nextItemSort(FaqCategory $category): int;

    /**
     * @param  list<int>  $ids  category ids in their new order
     */
    public function reorderCategories(array $ids): void;

    /**
     * @param  list<int>  $ids  item ids of the category in their new order
     */
    public function reorderItems(FaqCategory $category, array $ids): void;
}
