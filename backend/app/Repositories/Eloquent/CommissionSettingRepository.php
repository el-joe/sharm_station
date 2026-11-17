<?php

namespace App\Repositories\Eloquent;

use App\Enums\CommissionCategory;
use App\Models\CommissionSetting;
use App\Repositories\Contracts\CommissionSettingRepositoryInterface;

class CommissionSettingRepository implements CommissionSettingRepositoryInterface
{
    public function percents(): array
    {
        return CommissionSetting::query()->get()
            ->mapWithKeys(fn (CommissionSetting $setting): array => [$setting->category->value => (float) $setting->percent])
            ->all();
    }

    public function save(CommissionCategory $category, float $percent): void
    {
        CommissionSetting::query()->updateOrCreate(['category' => $category->value], ['percent' => $percent]);
    }
}
