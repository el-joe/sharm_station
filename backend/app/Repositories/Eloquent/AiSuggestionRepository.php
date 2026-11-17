<?php

namespace App\Repositories\Eloquent;

use App\Enums\SuggestionStatus;
use App\Models\AiSuggestion;
use App\Repositories\Contracts\AiSuggestionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<AiSuggestion>
 */
class AiSuggestionRepository extends BaseRepository implements AiSuggestionRepositoryInterface
{
    protected function model(): string
    {
        return AiSuggestion::class;
    }

    public function adminQuery(?string $type = null, ?string $status = null): Builder
    {
        return $this->query()
            ->select('ai_suggestions.*')
            ->with(['token:id,name', 'reviewer:id,name'])
            ->when($type, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    public function countsByStatus(): array
    {
        $counts = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all();
        $result = ['all' => array_sum($counts)];

        foreach (SuggestionStatus::cases() as $status) {
            $result[$status->value] = $counts[$status->value] ?? 0;
        }

        return $result;
    }

    public function findForReview(int $id, bool $lock = false): AiSuggestion
    {
        return $this->query()
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->with(['subject', 'token:id,name', 'creator:id,name', 'reviewer:id,name'])
            ->findOrFail($id);
    }
}
