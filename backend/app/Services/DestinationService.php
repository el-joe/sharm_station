<?php

namespace App\Services;

use App\DTOs\Destination\DestinationData;
use App\Exceptions\DomainValidationException;
use App\Models\Destination;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DestinationService
{
    public function __construct(private readonly DestinationRepositoryInterface $destinations) {}

    /**
     * @return Collection<int, Destination>
     */
    public function active(): Collection
    {
        return $this->destinations->active();
    }

    public function findActiveBySlug(string $slug): ?Destination
    {
        return $this->destinations->findActiveBySlug($slug);
    }

    /**
     * @return Builder<Destination>
     */
    public function adminQuery(): Builder
    {
        return $this->destinations->adminQuery();
    }

    public function create(DestinationData $data): Destination
    {
        return $this->destinations->create($data->toAttributes());
    }

    public function update(Destination $destination, DestinationData $data): Destination
    {
        return $this->destinations->update($destination, $data->toAttributes());
    }

    /**
     * @throws DomainValidationException when trips or buildings still use the destination.
     */
    public function delete(Destination $destination): void
    {
        if ($this->destinations->isInUse($destination)) {
            throw DomainValidationException::withErrors(['destination' => ['Trips or buildings still use this destination. Move them first or deactivate it.']]);
        }

        $this->destinations->delete($destination);
    }
}
