<?php

namespace App\Repositories\Contracts;

use App\Enums\CommissionCategory;

interface CommissionSettingRepositoryInterface
{
    /**
     * Global percentage per category value (missing categories are absent).
     *
     * @return array<string, float>
     */
    public function percents(): array;

    public function save(CommissionCategory $category, float $percent): void;
}
