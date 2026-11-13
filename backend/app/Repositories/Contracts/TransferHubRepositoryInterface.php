<?php

namespace App\Repositories\Contracts;

use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<TransferHub>
 */
interface TransferHubRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, TransferHub>
     */
    public function enabled(): Collection;

    public function findEnabled(int $id): ?TransferHub;

    /**
     * Enabled hubs matching every token on name or the destination they serve.
     *
     * @param  list<string>  $tokens
     * @return Collection<int, TransferHub>
     */
    public function suggest(array $tokens, int $limit): Collection;

    /**
     * Enabled fixed-route places of a hub matching every token (its predefined destinations).
     *
     * @param  list<string>  $tokens
     * @return Collection<int, TransferFixedRoute>
     */
    public function suggestPlaces(int $hubId, array $tokens, int $limit): Collection;
}
