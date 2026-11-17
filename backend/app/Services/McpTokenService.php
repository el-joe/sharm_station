<?php

namespace App\Services;

use App\Exceptions\DomainValidationException;
use App\Models\User;
use App\Repositories\Contracts\McpTokenRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Staff-created access tokens for the Claude MCP server (CR-14).
 *
 * A token is accepted by /mcp only when it explicitly carries the "mcp" ability (a "*" token is not enough)
 * and belongs to a staff user; an MCP token is refused everywhere else (see AppServiceProvider).
 */
class McpTokenService
{
    public const ABILITY = 'mcp';

    public function __construct(private readonly McpTokenRepositoryInterface $tokens) {}

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function all(): Collection
    {
        return $this->tokens->all();
    }

    /**
     * @throws DomainValidationException when the owner is not a staff member.
     */
    public function create(User $owner, string $name): NewAccessToken
    {
        if (! $owner->isStaff()) {
            throw DomainValidationException::withErrors(['name' => ['Only staff members can own MCP tokens.']]);
        }

        return $owner->createToken($name, [self::ABILITY]);
    }

    public function revoke(int $tokenId): void
    {
        $token = $this->tokens->find($tokenId);
        abort_if($token === null, 404);

        $this->tokens->revoke($token);
    }

    public static function isRevoked(PersonalAccessToken $token): bool
    {
        return $token->expires_at !== null && $token->expires_at->isPast();
    }

    /**
     * Whether a token may open an MCP session: explicit "mcp" ability, not expired, owner is staff.
     */
    public static function grantsMcpAccess(?PersonalAccessToken $token, mixed $owner): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::ABILITY, $token->abilities ?? [], true)
            && ! self::isRevoked($token)
            && $owner instanceof User
            && $owner->isStaff();
    }

    public static function isMcpToken(PersonalAccessToken $token): bool
    {
        return in_array(self::ABILITY, $token->abilities ?? [], true);
    }
}
