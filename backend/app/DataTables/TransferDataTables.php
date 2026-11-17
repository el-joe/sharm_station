<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use App\Services\TransferService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Transfer lists: hubs, vehicles, fixed routes and customer requests.
 */
class TransferDataTables
{
    use RendersRowActions;

    public function __construct(private readonly TransferService $transfers) {}

    public function hubs(): JsonResponse
    {
        return (new EloquentDataTable($this->transfers->hubsQuery()))
            ->editColumn('name', fn (TransferHub $hub): string => $this->titleCell((string) $hub->name, $hub->type->label(), route('admin.transfers.hubs.edit', $hub)))
            ->addColumn('pricing', fn (TransferHub $hub): string => e('Base '.$this->money($hub->base_fare).' · min '.$this->money($hub->min_fare).' · '.count($hub->distance_tiers ?? []).' tiers'))
            ->editColumn('radius_km', fn (TransferHub $hub): string => e(number_format($hub->radius_km, 0).' km'))
            ->editColumn('is_enabled', fn (TransferHub $hub): string => StatusChip::html($hub->is_enabled ? 'enabled' : 'disabled'))
            ->addColumn('actions', fn (TransferHub $hub): string => $this->actions(
                $this->editLink(route('admin.transfers.hubs.edit', $hub)),
                $this->deleteForm(route('admin.transfers.hubs.destroy', $hub), 'Delete the hub “'.$hub->name.'” and its fixed routes?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where('transfer_hubs.name->en', 'like', "%{$keyword}%"))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('transfer_hubs.name->en', $direction))
            ->rawColumns(['name', 'pricing', 'radius_km', 'is_enabled', 'actions'])
            ->toJson();
    }

    public function vehicles(): JsonResponse
    {
        return (new EloquentDataTable($this->transfers->vehiclesQuery()))
            ->editColumn('name', fn (VehicleType $vehicle): string => $this->titleCell((string) $vehicle->name, $vehicle->category->label(), route('admin.transfers.vehicles.edit', $vehicle)))
            ->editColumn('multiplier', fn (VehicleType $vehicle): string => e('× '.(float) $vehicle->multiplier))
            ->editColumn('is_enabled', fn (VehicleType $vehicle): string => StatusChip::html($vehicle->is_enabled ? 'enabled' : 'disabled'))
            ->addColumn('actions', fn (VehicleType $vehicle): string => $this->actions(
                $this->editLink(route('admin.transfers.vehicles.edit', $vehicle)),
                $this->deleteForm(route('admin.transfers.vehicles.destroy', $vehicle), 'Delete the vehicle “'.$vehicle->name.'”?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where('vehicle_types.name->en', 'like', "%{$keyword}%"))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('vehicle_types.name->en', $direction))
            ->rawColumns(['name', 'multiplier', 'is_enabled', 'actions'])
            ->toJson();
    }

    public function routes(): JsonResponse
    {
        return (new EloquentDataTable($this->transfers->fixedRoutesQuery()))
            ->addColumn('destination', fn (TransferFixedRoute $route): string => $this->titleCell((string) $route->destination_name, round($route->lat, 4).', '.round($route->lng, 4), route('admin.transfers.routes.edit', $route)))
            ->addColumn('hub', fn (TransferFixedRoute $route): string => e((string) ($route->hub?->name ?? '—')))
            ->addColumn('vehicle', fn (TransferFixedRoute $route): string => e($route->vehicleType ? (string) $route->vehicleType->name : 'All (× multiplier)'))
            ->editColumn('price', fn (TransferFixedRoute $route): string => $this->money($route->price))
            ->editColumn('is_enabled', fn (TransferFixedRoute $route): string => StatusChip::html($route->is_enabled ? 'enabled' : 'disabled'))
            ->addColumn('actions', fn (TransferFixedRoute $route): string => $this->actions(
                $this->editLink(route('admin.transfers.routes.edit', $route)),
                $this->deleteForm(route('admin.transfers.routes.destroy', $route), 'Delete this fixed route?'),
            ))
            ->filterColumn('destination', fn (Builder $query, string $keyword) => $query->where('transfer_fixed_routes.destination_name->en', 'like', "%{$keyword}%"))
            ->rawColumns(['destination', 'hub', 'vehicle', 'price', 'is_enabled', 'actions'])
            ->toJson();
    }

    public function requests(?string $status = null): JsonResponse
    {
        return (new EloquentDataTable($this->transfers->requestsQuery($status)))
            ->addColumn('reference', fn (TransferRequest $request): string => $this->titleCell((string) ($request->booking?->reference ?? '#'.$request->id), $request->booking?->lead_name, route('admin.transfers.requests.show', $request)))
            ->editColumn('pickup_at', fn (TransferRequest $request): string => e($request->pickup_at->format('j M Y H:i')).($request->round_trip ? '<div class="text-xs text-ink-500">Return '.e((string) $request->return_at?->format('j M H:i')).'</div>' : ''))
            ->addColumn('route', fn (TransferRequest $request): string => e((string) ($request->hub?->name ?? '—')).' → '.e($request->destination_name).'<div class="text-xs text-ink-500">'.e((float) $request->distance_km.' km'.($request->needs_review ? ' · estimated' : '')).'</div>')
            ->addColumn('vehicle', fn (TransferRequest $request): string => e((string) ($request->vehicleType?->name ?? '—')).' · '.$request->passengers.' pax')
            ->editColumn('price', fn (TransferRequest $request): string => $this->money($request->price))
            ->editColumn('status', fn (TransferRequest $request): string => StatusChip::html($request->status))
            ->addColumn('actions', fn (TransferRequest $request): string => $this->actions($this->editLink(route('admin.transfers.requests.show', $request), 'Open')))
            ->filterColumn('reference', fn (Builder $query, string $keyword) => $query->whereHas('booking', fn (Builder $booking) => $booking
                ->where('reference', 'like', "%{$keyword}%")->orWhere('lead_name', 'like', "%{$keyword}%")))
            ->rawColumns(['reference', 'pickup_at', 'route', 'vehicle', 'price', 'status', 'actions'])
            ->toJson();
    }
}
