<?php

namespace App\Services;

use App\DTOs\Commission\CommissionPercentsData;
use App\Enums\CommissionCategory;
use App\Models\Partner;
use App\Repositories\Contracts\CommissionSettingRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Services\Pricing\CommissionResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Commission settings (CR-11): global percentage per type and per-partner overrides.
 */
class CommissionService
{
    public function __construct(
        private readonly CommissionSettingRepositoryInterface $settings,
        private readonly PartnerRepositoryInterface $partners,
        private readonly CommissionResolver $resolver,
    ) {}

    /**
     * @return array<string, float>
     */
    public function globalPercents(): array
    {
        return $this->resolver->globalPercents();
    }

    public function saveGlobal(CommissionPercentsData $data): void
    {
        DB::transaction(function () use ($data): void {
            foreach (CommissionCategory::cases() as $category) {
                $this->settings->save($category, (float) ($data->percents[$category->value] ?? 0));
            }
        });

        $this->resolver->flush();
    }

    /**
     * Overrides of one partner; empty values remove the override (the global setting applies).
     */
    public function saveOverrides(Partner $partner, CommissionPercentsData $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            foreach (CommissionCategory::cases() as $category) {
                $this->partners->setCategoryCommission($partner, $category, $data->percents[$category->value] ?? null);
            }

            return $partner->load('categoryCommissions');
        });
    }

    /**
     * Effective rate per type for a partner (or the global rates without one).
     *
     * @return list<array<string, mixed>>
     */
    public function effectiveFor(?Partner $partner): array
    {
        return $this->resolver->effectiveFor($partner);
    }

    /**
     * Partners with at least one override, for the settings page.
     *
     * @return Collection<int, Partner>
     */
    public function partnersWithOverrides(): Collection
    {
        return $this->partners->withCommissionOverrides();
    }
}
