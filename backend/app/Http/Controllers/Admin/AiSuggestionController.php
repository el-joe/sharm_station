<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AiSuggestionDataTable;
use App\DTOs\Ai\AccommodationSuggestionData;
use App\DTOs\Ai\SeoValuesData;
use App\DTOs\Ai\SuggestionFilterData;
use App\Enums\BuildingType;
use App\Enums\RoomType;
use App\Enums\SeoSubjectType;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccommodationSuggestionRequest;
use App\Http\Requests\Admin\RejectSuggestionRequest;
use App\Http\Requests\Admin\SeoSuggestionRequest;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Services\AiSuggestionService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * AI suggestions inbox (CR-14): review, edit, approve (applies the change) or reject suggestions created by Claude.
 */
class AiSuggestionController extends Controller
{
    public function __construct(
        private readonly AiSuggestionService $suggestions,
        private readonly SeoService $seo,
        private readonly DestinationRepositoryInterface $destinations,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.ai-suggestions.index', [
            'types' => SuggestionType::options(),
            'statuses' => SuggestionStatus::options(),
            'counts' => $this->suggestions->counts(),
            'status' => SuggestionStatus::tryFrom((string) $request->query('status', SuggestionStatus::Pending->value))?->value,
        ]);
    }

    public function data(Request $request, AiSuggestionDataTable $table): JsonResponse
    {
        return $table->json(SuggestionFilterData::fromArray($request->only(['type', 'status'])));
    }

    public function show(int $suggestion): View
    {
        $suggestion = $this->suggestions->findForReview($suggestion);

        if ($suggestion->type === SuggestionType::SeoUpdate) {
            $subjectType = SeoSubjectType::tryFrom((string) ($suggestion->payload['subject_type'] ?? ''));

            return view('admin.ai-suggestions.seo', [
                'suggestion' => $suggestion,
                'subjectType' => $subjectType,
                'current' => $suggestion->subject && $suggestion->isPending() ? $this->seo->current($suggestion->subject)->toArray() : ($suggestion->original ?? []),
                'subjectUrl' => $this->subjectUrl($suggestion->subject, $subjectType),
            ]);
        }

        return view('admin.ai-suggestions.accommodation', [
            'suggestion' => $suggestion,
            'destinations' => $this->destinations->active()->mapWithKeys(fn ($destination): array => [$destination->slug => (string) $destination->name])->all(),
            'buildingTypes' => BuildingType::options(),
            'roomTypes' => RoomType::options(),
            'canApprove' => Gate::allows('manage-prices'),
            'checks' => $suggestion->isPending() ? $this->suggestions->accommodationChecks($suggestion) : [],
        ]);
    }

    public function approveSeo(SeoSuggestionRequest $request, int $suggestion): RedirectResponse
    {
        $this->suggestions->approveSeo($suggestion, SeoValuesData::fromArray($request->validated()), $request->user());

        return redirect()->route('admin.ai-suggestions.show', $suggestion)->with('success', 'SEO suggestion approved and applied.');
    }

    public function approveAccommodation(AccommodationSuggestionRequest $request, int $suggestion): RedirectResponse
    {
        $approved = $this->suggestions->approveAccommodation($suggestion, AccommodationSuggestionData::fromArray($request->validated()), $request->boolean('publish'), $request->user());

        return redirect()->route('admin.buildings.edit', $approved->subject_id)
            ->with('success', 'Suggestion approved: building and rooms created'.($request->boolean('publish') ? ' and published.' : ' as a draft.'));
    }

    public function reject(RejectSuggestionRequest $request, int $suggestion): RedirectResponse
    {
        $this->suggestions->reject($suggestion, $request->validated('review_note'), $request->user());

        return redirect()->route('admin.ai-suggestions.index')->with('success', 'Suggestion rejected. Nothing was changed.');
    }

    private function subjectUrl(mixed $subject, ?SeoSubjectType $type): ?string
    {
        if ($subject === null || $type === null) {
            return null;
        }

        return match ($type) {
            SeoSubjectType::Trip => route('admin.trips.edit', $subject),
            SeoSubjectType::Package => route('admin.packages.edit', $subject),
            SeoSubjectType::Building => route('admin.buildings.edit', $subject),
            SeoSubjectType::Room => route('admin.rooms.edit', $subject),
            SeoSubjectType::TransferRoute => route('admin.transfers.routes.edit', $subject),
        };
    }
}
