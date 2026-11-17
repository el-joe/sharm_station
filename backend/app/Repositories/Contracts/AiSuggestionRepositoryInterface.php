<?php

namespace App\Repositories\Contracts;

use App\Models\AiSuggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<AiSuggestion>
 */
interface AiSuggestionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Admin inbox query with the relations the list renders.
     *
     * @return Builder<AiSuggestion>
     */
    public function adminQuery(?string $type = null, ?string $status = null): Builder;

    /**
     * Count per status value, plus "all".
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array;

    /**
     * Suggestion with subject, token, creator and reviewer loaded, locked for update when $lock is true.
     */
    public function findForReview(int $id, bool $lock = false): AiSuggestion;
}
