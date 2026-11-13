<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Destination;
use App\Services\DestinationService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class DestinationDataTable
{
    use RendersRowActions;

    public function __construct(private readonly DestinationService $destinations) {}

    public function json(): JsonResponse
    {
        return (new EloquentDataTable($this->destinations->adminQuery()))
            ->addColumn('name', fn (Destination $destination): string => $this->titleCell((string) $destination->name, $destination->slug, route('admin.destinations.edit', $destination)))
            ->editColumn('is_active', fn (Destination $destination): string => StatusChip::html($destination->is_active ? 'active' : 'inactive'))
            ->addColumn('actions', fn (Destination $destination): string => $this->actions(
                $this->editLink(route('admin.destinations.edit', $destination)),
                $this->deleteForm(route('admin.destinations.destroy', $destination), 'Delete the destination “'.$destination->name.'”?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('destinations.name->en', 'like', "%{$keyword}%")->orWhere('destinations.name->it', 'like', "%{$keyword}%")->orWhere('destinations.slug', 'like', "%{$keyword}%")))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('destinations.name->en', $direction))
            ->rawColumns(['name', 'is_active', 'actions'])
            ->toJson();
    }
}
