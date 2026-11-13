<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Services\PartnerService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class PartnerDataTable
{
    use RendersRowActions;

    public function __construct(private readonly PartnerService $partners) {}

    public function json(?PartnerStatus $status): JsonResponse
    {
        return (new EloquentDataTable($this->partners->adminQuery($status)))
            ->editColumn('company', fn (Partner $partner): string => $this->titleCell($partner->company, trim(($partner->country ? $partner->country.' · ' : '').($partner->website ?? ''))))
            ->addColumn('contact', fn (Partner $partner): string => $this->titleCell((string) $partner->user?->name, $partner->user?->email))
            ->editColumn('status', fn (Partner $partner): string => StatusChip::html($partner->status))
            ->editColumn('default_commission', fn (Partner $partner): string => $partner->default_commission !== null ? e((float) $partner->default_commission.'%') : '—')
            ->editColumn('sales_total', fn (Partner $partner): string => $this->money($partner->sales_total ?? 0))
            ->editColumn('created_at', fn (Partner $partner): string => e($partner->created_at?->format('j M Y') ?? ''))
            ->addColumn('actions', fn (Partner $partner): string => $this->actions(...$this->statusActions($partner)))
            ->filterColumn('company', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('partners.company', 'like', "%{$keyword}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%"))))
            ->rawColumns(['company', 'contact', 'status', 'default_commission', 'sales_total', 'created_at', 'actions'])
            ->toJson();
    }

    /**
     * @return list<string>
     */
    private function statusActions(Partner $partner): array
    {
        $form = fn (string $route, string $label, string $class, string $confirm): string => '<form method="POST" action="'.e(route($route, $partner)).'" data-confirm="'.e($confirm).'" data-confirm-ok="'.e($label).'" data-confirm-title="'.e($label.' partner').'">'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<button type="submit" class="text-sm font-semibold '.$class.' hover:underline">'.e($label).'</button></form>';

        return match ($partner->status) {
            PartnerStatus::Pending => [$form('admin.partners.approve', 'Approve', 'text-success', "Approve {$partner->company}? The user becomes a partner and sees partner prices.")],
            PartnerStatus::Active => [$form('admin.partners.suspend', 'Suspend', 'text-error', "Suspend {$partner->company}? They will see retail prices until reactivated.")],
            PartnerStatus::Suspended => [$form('admin.partners.approve', 'Reactivate', 'text-success', "Reactivate {$partner->company}?")],
        };
    }
}
