<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Addon;
use App\Services\AddonService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class AddonDataTable
{
    use RendersRowActions;

    public function __construct(private readonly AddonService $addons) {}

    public function json(bool $showPrices): JsonResponse
    {
        return (new EloquentDataTable($this->addons->adminQuery()))
            ->addColumn('name', fn (Addon $addon): string => $this->titleCell((string) $addon->name, $addon->required ? 'Required' : 'Optional', $showPrices ? route('admin.addons.edit', $addon) : null))
            ->editColumn('pricing_basis', fn (Addon $addon): string => e($addon->pricing_basis->label()))
            ->editColumn('price', fn (Addon $addon): string => $showPrices ? $this->money($addon->price) : '—')
            ->editColumn('is_active', fn (Addon $addon): string => StatusChip::html($addon->is_active ? 'active' : 'inactive'))
            ->addColumn('actions', fn (Addon $addon): string => $showPrices ? $this->actions(
                $this->editLink(route('admin.addons.edit', $addon)),
                $this->deleteForm(route('admin.addons.destroy', $addon), 'Delete the add-on “'.$addon->name.'”? It is removed from every trip.'),
            ) : '')
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('addons.name->en', 'like', "%{$keyword}%")->orWhere('addons.name->it', 'like', "%{$keyword}%")))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('addons.name->en', $direction))
            ->rawColumns(['name', 'pricing_basis', 'price', 'is_active', 'actions'])
            ->toJson();
    }
}
