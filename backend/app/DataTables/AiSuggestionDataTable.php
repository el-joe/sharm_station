<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Ai\SuggestionFilterData;
use App\Enums\SuggestionType;
use App\Models\AiSuggestion;
use App\Services\AiSuggestionService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * AI suggestions inbox (CR-14).
 */
class AiSuggestionDataTable
{
    use RendersRowActions;

    public function __construct(private readonly AiSuggestionService $suggestions) {}

    public function json(SuggestionFilterData $filters): JsonResponse
    {
        return (new EloquentDataTable($this->suggestions->adminQuery($filters)))
            ->editColumn('id', fn (AiSuggestion $suggestion): string => '<span class="tabular-nums text-ink-500">'.$suggestion->id.'</span>')
            ->editColumn('type', fn (AiSuggestion $suggestion): string => StatusChip::html($suggestion->type->value, $suggestion->type->label(), $suggestion->type === SuggestionType::AccommodationCreate ? 'warning' : 'primary'))
            ->addColumn('subject', fn (AiSuggestion $suggestion): string => $this->titleCell($suggestion->title(), $this->summary($suggestion), route('admin.ai-suggestions.show', $suggestion)))
            ->editColumn('source', fn (AiSuggestion $suggestion): string => e('Claude (MCP)'.($suggestion->token ? ' · '.$suggestion->token->name : '')))
            ->editColumn('created_at', fn (AiSuggestion $suggestion): string => e($suggestion->created_at->format('j M Y, H:i')))
            ->editColumn('status', fn (AiSuggestion $suggestion): string => StatusChip::html($suggestion->status))
            ->addColumn('actions', fn (AiSuggestion $suggestion): string => $this->actions($suggestion->isPending()
                ? '<a href="'.e(route('admin.ai-suggestions.show', $suggestion)).'" class="btn-primary btn-sm">Review</a>'
                : $this->editLink(route('admin.ai-suggestions.show', $suggestion), 'View')))
            ->filterColumn('subject', fn (Builder $query, string $keyword) => $query->where(fn (Builder $any) => $any
                ->where('ai_suggestions.id', ltrim($keyword, '#'))
                ->orWhereRaw('LOWER(CAST(ai_suggestions.payload AS CHAR)) LIKE ?', ['%'.mb_strtolower(addcslashes($keyword, '%_\\')).'%'])))
            ->rawColumns(['id', 'type', 'subject', 'source', 'created_at', 'status', 'actions'])
            ->toJson();
    }

    /**
     * "Hurghada · 5★ · 4 room types" or "Meta title, keywords, slug · EN, IT".
     */
    private function summary(AiSuggestion $suggestion): string
    {
        $payload = $suggestion->payload ?? [];

        if ($suggestion->type === SuggestionType::AccommodationCreate) {
            $rooms = count($payload['rooms'] ?? []);

            return implode(' · ', array_filter([
                $payload['building']['destination_slug'] ?? null,
                isset($payload['building']['stars']) ? $payload['building']['stars'].'★' : null,
                $rooms.' '.str('room type')->plural($rooms),
            ]));
        }

        $fields = collect($payload['locales'] ?? [])->flatMap(fn (array $values): array => array_keys(array_filter($values)))->unique()
            ->map(fn (string $field): string => str_replace(['meta_', 'og_'], ['Meta ', 'OG '], $field))->values()->all();

        if (! empty($payload['slug'])) {
            $fields[] = 'slug';
        }

        return ucfirst(implode(', ', $fields)).' · '.strtoupper(implode(', ', array_keys($payload['locales'] ?? [])));
    }
}
