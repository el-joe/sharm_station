<?php

namespace App\Repositories\Contracts;

use App\Models\Page;

/**
 * @extends BaseRepositoryInterface<Page>
 */
interface PageRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Page;
}
