<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\McpTokenRepositoryInterface;
use App\Services\McpTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class McpTokenRepository implements McpTokenRepositoryInterface
{
    public function all(): Collection
    {
        return $this->query()->with('tokenable')->latest('id')->get();
    }

    public function find(int $id): ?PersonalAccessToken
    {
        return $this->query()->find($id);
    }

    /**
     * Revoked tokens are expired, not deleted, so suggestions keep the token name that created them.
     */
    public function revoke(PersonalAccessToken $token): void
    {
        $token->forceFill(['expires_at' => now()])->save();
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function query(): Builder
    {
        return PersonalAccessToken::query()->whereJsonContains('abilities', McpTokenService::ABILITY);
    }
}
