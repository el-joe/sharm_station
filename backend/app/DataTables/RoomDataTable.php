<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Room;
use App\Services\RoomService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class RoomDataTable
{
    use RendersRowActions;

    public function __construct(private readonly RoomService $rooms) {}

    public function json(?int $buildingId, bool $showPrices): JsonResponse
    {
        return (new EloquentDataTable($this->rooms->adminQuery($buildingId)))
            ->addColumn('name', fn (Room $room): string => $this->titleCell((string) $room->name, $room->number ? 'Room '.$room->number : null, route('admin.rooms.edit', $room)))
            ->addColumn('building', fn (Room $room): string => e((string) $room->building?->name))
            ->editColumn('room_type', fn (Room $room): string => e($room->room_type->label()))
            ->addColumn('capacity', fn (Room $room): string => e("{$room->max_adults} ad · {$room->max_children} ch · {$room->max_total} max"))
            ->editColumn('base_price', fn (Room $room): string => $showPrices ? $this->money($room->base_price).' <span class="text-xs text-ink-500">/'.($room->pricing_mode->value === 'per_person' ? 'person' : 'room').'</span>' : '—')
            ->editColumn('status', fn (Room $room): string => StatusChip::html($room->status))
            ->addColumn('actions', fn (Room $room): string => $this->actions(
                '<a href="'.e(route('admin.rooms.calendar', $room)).'" class="btn-link">Calendar</a>',
                $this->editLink(route('admin.rooms.edit', $room)),
                $this->deleteForm(route('admin.rooms.destroy', $room), 'Delete the room “'.$room->name.'”?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('rooms.name->en', 'like', "%{$keyword}%")->orWhere('rooms.number', 'like', "%{$keyword}%")))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('rooms.name->en', $direction))
            ->rawColumns(['name', 'building', 'room_type', 'capacity', 'base_price', 'status', 'actions'])
            ->toJson();
    }
}
