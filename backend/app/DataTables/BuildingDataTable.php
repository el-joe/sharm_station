<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Building;
use App\Services\BuildingService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class BuildingDataTable
{
    use RendersRowActions;

    public function __construct(private readonly BuildingService $buildings) {}

    public function json(?int $destinationId = null): JsonResponse
    {
        return (new EloquentDataTable($this->buildings->adminQuery($destinationId)))
            ->addColumn('name', fn (Building $building): string => $this->titleCell((string) $building->name, $building->type->label(), route('admin.buildings.edit', $building)))
            ->addColumn('destination', fn (Building $building): string => e((string) $building->destination?->name))
            ->editColumn('stars', fn (Building $building): string => $building->stars ? str_repeat('★', $building->stars) : '—')
            ->editColumn('taxes_included', fn (Building $building): string => $building->taxes_included
                ? StatusChip::html('included', 'Tax included', 'success')
                : StatusChip::html('extra', 'Tax '.$this->money($building->tax_per_night).'/night', 'warning'))
            ->editColumn('status', fn (Building $building): string => StatusChip::html($building->status))
            ->addColumn('actions', fn (Building $building): string => $this->actions(
                '<a href="'.e(route('admin.rooms.index', ['building_id' => $building->id])).'" class="btn-link">Rooms</a>',
                $this->editLink(route('admin.buildings.edit', $building)),
                $this->deleteForm(route('admin.buildings.destroy', $building), 'Delete “'.$building->name.'” and all its rooms?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('buildings.name->en', 'like', "%{$keyword}%")->orWhere('buildings.slug', 'like', "%{$keyword}%")->orWhere('buildings.address', 'like', "%{$keyword}%")))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('buildings.name->en', $direction))
            ->rawColumns(['name', 'destination', 'stars', 'taxes_included', 'status', 'actions'])
            ->toJson();
    }
}
