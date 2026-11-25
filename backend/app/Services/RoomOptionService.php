<?php

namespace App\Services;

use App\DTOs\Room\RoomOptionData;
use App\Enums\ActivityAction;
use App\Enums\TripType;
use App\Models\Package;
use App\Models\RoomOption;
use App\Models\Trip;
use App\Repositories\Contracts\RoomOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin management of the optional rooms of multi-day trips, Nile trips and package stages (SRS 7.1).
 */
class RoomOptionService
{
    public function __construct(
        private readonly RoomOptionRepositoryInterface $options,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return Collection<int, RoomOption>
     */
    public function forOwner(Trip|Package $owner): Collection
    {
        return $this->options->forOwner($owner);
    }

    /**
     * Rooms are only offered on multi-day products (a single-day trip has no night).
     */
    public function supports(Trip|Package $owner): bool
    {
        return $owner instanceof Package || ($owner->type !== TripType::SingleDay && $owner->nights > 0);
    }

    public function create(Trip|Package $owner, RoomOptionData $data): RoomOption
    {
        /** @var RoomOption $option */
        $option = $this->options->create([...$data->toAttributes(), 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()]);
        $this->log(ActivityAction::Created, $option, $owner, 'added');

        return $option;
    }

    public function update(RoomOption $option, RoomOptionData $data): RoomOption
    {
        $attributes = $data->toAttributes();

        if (! $option->owner instanceof Package) {
            $attributes['stage_position'] = null;
        }

        $this->options->update($option, $attributes);
        $this->log(ActivityAction::Updated, $option, $option->owner, 'updated');

        return $option;
    }

    public function delete(RoomOption $option): void
    {
        $owner = $option->owner;
        $this->options->delete($option);
        $this->log(ActivityAction::Deleted, $option, $owner, 'removed');
    }

    private function log(ActivityAction $action, RoomOption $option, ?Model $owner, string $verb): void
    {
        $product = $owner instanceof Package || $owner instanceof Trip ? (string) $owner->title : 'product';
        $this->activity->log($action, $owner ?? $option, "Optional room “{$option->label()}” {$verb} on {$product}", ['room_option_id' => $option->id]);
    }
}
