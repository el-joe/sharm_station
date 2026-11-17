<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum personal access tokens carrying the "mcp" ability (Claude MCP access, CR-14).
 */
interface McpTokenRepositoryInterface
{
    /**
     * Every MCP token (active and revoked), newest first, with its owner.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function all(): Collection;

    public function find(int $id): ?PersonalAccessToken;

    public function revoke(PersonalAccessToken $token): void;
}
