<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Package;
use App\Services\PackageService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class PackageDataTable
{
    use RendersRowActions;

    public function __construct(private readonly PackageService $packages) {}

    public function json(bool $canManage, ?string $status = null): JsonResponse
    {
        return (new EloquentDataTable($this->packages->adminQuery($status)))
            ->addColumn('title', fn (Package $package): string => $this->titleCell((string) $package->title, $package->days.' days · '.$package->nights.' nights', $canManage ? route('admin.packages.edit', $package) : null))
            ->addColumn('destination', fn (Package $package): string => e((string) ($package->destination?->name ?? '—')))
            ->editColumn('price_mode', fn (Package $package): string => e($package->price_mode->label()))
            ->editColumn('from_price', fn (Package $package): string => $canManage ? $this->money($package->from_price ?? $package->base_price) : '—')
            ->editColumn('status', fn (Package $package): string => StatusChip::html($package->status))
            ->addColumn('actions', fn (Package $package): string => $canManage ? $this->actions(
                $this->editLink(route('admin.packages.edit', $package)),
                $this->deleteForm(route('admin.packages.destroy', $package), 'Delete the package “'.$package->title.'”?'),
            ) : '')
            ->filterColumn('title', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('packages.title->en', 'like', "%{$keyword}%")->orWhere('packages.title->it', 'like', "%{$keyword}%")))
            ->orderColumn('title', fn (Builder $query, string $direction) => $query->orderBy('packages.title->en', $direction))
            ->rawColumns(['title', 'destination', 'price_mode', 'from_price', 'status', 'actions'])
            ->toJson();
    }
}
