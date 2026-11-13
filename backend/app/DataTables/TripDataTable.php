<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Trip\TripAdminFilterData;
use App\Models\Trip;
use App\Services\TripService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Server-side list of trips / Nile trips.
 */
class TripDataTable
{
    use RendersRowActions;

    public function __construct(private readonly TripService $trips) {}

    public function json(TripAdminFilterData $filters, bool $showPrices): JsonResponse
    {
        return (new EloquentDataTable($this->trips->adminQuery($filters)))
            ->addColumn('title', fn (Trip $trip): string => $this->titleCell((string) $trip->title, $trip->slug, route('admin.trips.edit', $trip)))
            ->addColumn('destination', fn (Trip $trip): string => e((string) $trip->destination?->name))
            ->editColumn('type', fn (Trip $trip): string => e($trip->type->label()))
            ->editColumn('pricing_mode', fn (Trip $trip): string => e($trip->pricing_mode->label()))
            ->editColumn('from_price', fn (Trip $trip): string => $showPrices ? $this->money($trip->from_price) : '—')
            ->editColumn('status', fn (Trip $trip): string => StatusChip::html($trip->status).($trip->featured ? ' <span class="chip border-gold-500/40 bg-brand-100 text-brand-700">Featured</span>' : ''))
            ->addColumn('actions', fn (Trip $trip): string => $this->actions(
                $this->editLink(route('admin.trips.edit', $trip)),
                $this->deleteForm(route('admin.trips.destroy', $trip), 'Delete “'.$trip->title.'”? Bookings keep their copy of the data.'),
            ))
            ->filterColumn('title', fn (Builder $query, string $keyword) => $query->where(function (Builder $query) use ($keyword): void {
                $query->where('trips.title->en', 'like', "%{$keyword}%")
                    ->orWhere('trips.title->it', 'like', "%{$keyword}%")
                    ->orWhere('trips.slug', 'like', "%{$keyword}%");
            }))
            ->orderColumn('title', fn (Builder $query, string $direction) => $query->orderBy('trips.title->en', $direction))
            ->rawColumns(['title', 'destination', 'type', 'pricing_mode', 'from_price', 'status', 'actions'])
            ->toJson();
    }
}
