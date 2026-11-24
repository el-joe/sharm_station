<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Enums\CouponDiscountType;
use App\Enums\ServiceType;
use App\Models\Coupon;
use App\Services\CouponService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class CouponDataTable
{
    use RendersRowActions;

    public function __construct(private readonly CouponService $coupons) {}

    public function json(?bool $active = null): JsonResponse
    {
        return (new EloquentDataTable($this->coupons->adminQuery($active)))
            ->editColumn('code', fn (Coupon $coupon): string => $this->titleCell($coupon->code, $coupon->description, route('admin.coupons.edit', $coupon)))
            ->editColumn('value', fn (Coupon $coupon): string => e($coupon->discount_type === CouponDiscountType::Percent ? (float) $coupon->value.'%' : '€'.number_format((float) $coupon->value, 2)))
            ->addColumn('validity', fn (Coupon $coupon): string => e(match (true) {
                $coupon->valid_from === null && $coupon->valid_to === null => 'Always',
                $coupon->valid_to === null => 'From '.$coupon->valid_from->format('j M Y'),
                $coupon->valid_from === null => 'Until '.$coupon->valid_to->format('j M Y'),
                default => $coupon->valid_from->format('j M Y').' – '.$coupon->valid_to->format('j M Y'),
            }))
            ->addColumn('services_list', fn (Coupon $coupon): string => e($coupon->serviceTypes() === [] ? 'All services' : implode(', ', array_map(fn (ServiceType $service): string => $service->label(), $coupon->serviceTypes()))))
            ->editColumn('redemptions_count', fn (Coupon $coupon): string => e($coupon->redemptions_count.($coupon->usage_limit ? ' / '.$coupon->usage_limit : '')))
            ->editColumn('redemptions_sum_discount_amount', fn (Coupon $coupon): string => $this->money($coupon->redemptions_sum_discount_amount ?? 0))
            ->editColumn('is_active', fn (Coupon $coupon): string => StatusChip::html($coupon->is_active ? 'active' : 'inactive'))
            ->addColumn('actions', fn (Coupon $coupon): string => $this->actions(
                $this->editLink(route('admin.coupons.edit', $coupon)),
                $this->deleteForm(route('admin.coupons.destroy', $coupon), "Delete the coupon {$coupon->code}? Bookings keep the discount they received."),
            ))
            ->filterColumn('code', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('coupons.code', 'like', "%{$keyword}%")->orWhere('coupons.description', 'like', "%{$keyword}%")))
            ->rawColumns(['code', 'value', 'validity', 'services_list', 'redemptions_count', 'redemptions_sum_discount_amount', 'is_active', 'actions'])
            ->toJson();
    }
}
