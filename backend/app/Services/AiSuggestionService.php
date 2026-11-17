<?php

namespace App\Services;

use App\DTOs\Ai\AccommodationSuggestionData;
use App\DTOs\Ai\SeoValuesData;
use App\DTOs\Ai\SuggestionFilterData;
use App\DTOs\Building\BuildingData;
use App\DTOs\Room\RoomData;
use App\Enums\PublishStatus;
use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\SeoSubjectType;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Exceptions\DomainValidationException;
use App\Http\Requests\Admin\AccommodationSuggestionRequest;
use App\Models\AiSuggestion;
use App\Models\Building;
use App\Models\Package;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\AiSuggestionRepositoryInterface;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * AI suggestions inbox (CR-14): Claude creates pending suggestions through the MCP server;
 * an admin edits them and approves (the change is applied in one transaction) or rejects them with a reason.
 */
class AiSuggestionService
{
    public function __construct(
        private readonly AiSuggestionRepositoryInterface $suggestions,
        private readonly DestinationRepositoryInterface $destinations,
        private readonly BuildingService $buildings,
        private readonly RoomService $rooms,
        private readonly SeoService $seo,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return Builder<AiSuggestion>
     */
    public function adminQuery(SuggestionFilterData $filters): Builder
    {
        return $this->suggestions->adminQuery($filters->type?->value, $filters->status?->value);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->suggestions->countsByStatus();
    }

    public function findForReview(int $id): AiSuggestion
    {
        return $this->suggestions->findForReview($id);
    }

    /**
     * Review checklist of an accommodation suggestion: the stored payload against today's admin form rules.
     *
     * @return list<array{ok: bool, message: string}>
     */
    public function accommodationChecks(AiSuggestion $suggestion): array
    {
        $payload = $suggestion->payload ?? [];
        $errors = Validator::make($payload, AccommodationSuggestionRequest::payloadRules(), AccommodationSuggestionRequest::payloadMessages())->errors();
        $checks = [['ok' => $errors->isEmpty(), 'message' => $errors->isEmpty() ? 'Payload valid (same rules as the admin form)' : $errors->count().' field(s) need fixing: '.$errors->first()]];

        if (! $errors->has('building.slug')) {
            $checks[] = ['ok' => true, 'message' => 'Slug “'.($payload['building']['slug'] ?? '').'” is free'];
        }

        $rooms = count($payload['rooms'] ?? []);
        $checks[] = ['ok' => $rooms > 0, 'message' => $rooms > 0 ? $rooms.' '.str('room type')->plural($rooms).' proposed' : 'No rooms proposed: add them before approving, or later in the admin'];
        $checks[] = ['ok' => false, 'message' => 'No photos: add the gallery after approval'];
        $checks[] = ['ok' => false, 'message' => 'Prices are the base price per night; set nationality group and season prices in the price matrix after approval'];

        return $checks;
    }

    public function createAccommodation(AccommodationSuggestionData $data, ?User $creator, ?PersonalAccessToken $token): AiSuggestion
    {
        return $this->suggestions->create([
            'type' => SuggestionType::AccommodationCreate,
            'payload' => $data->toArray(),
            'original' => null,
            'status' => SuggestionStatus::Pending,
            'source' => 'mcp',
            'created_by_token_id' => $token?->id,
            'created_by_user_id' => $creator?->id,
        ]);
    }

    /**
     * Stores an SEO suggestion with the subject's current values in `original`.
     *
     * @throws DomainValidationException when a slug is proposed for a product without slug, or nothing is proposed.
     */
    public function createSeo(Model $subject, SeoValuesData $values, ?string $notes, ?User $creator, ?PersonalAccessToken $token): AiSuggestion
    {
        $type = SeoSubjectType::fromModel($subject);

        if ($values->isEmpty()) {
            throw DomainValidationException::withErrors(['locales' => ['Propose at least one SEO field (meta_title, meta_description, keywords, og_title, og_description) or a slug.']]);
        }

        if ($values->slug !== null && ! $type->hasSlug()) {
            throw DomainValidationException::withErrors(['slug' => ["A {$type->label()} has no URL slug: leave \"slug\" empty."]]);
        }

        return $this->suggestions->create([
            'type' => SuggestionType::SeoUpdate,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => [
                'subject_type' => $type->value,
                'subject_type_label' => $type->label(),
                'subject_label' => self::subjectLabel($subject),
                ...$values->toArray(),
                'notes' => $notes,
            ],
            'original' => $this->seo->current($subject)->toArray(),
            'status' => SuggestionStatus::Pending,
            'source' => 'mcp',
            'created_by_token_id' => $token?->id,
            'created_by_user_id' => $creator?->id,
        ]);
    }

    /**
     * Applies the (admin-edited) SEO values to the subject and marks the suggestion approved.
     *
     * @throws DomainValidationException
     */
    public function approveSeo(int $suggestionId, SeoValuesData $values, User $reviewer): AiSuggestion
    {
        return DB::transaction(function () use ($suggestionId, $values, $reviewer): AiSuggestion {
            $suggestion = $this->lockPending($suggestionId, SuggestionType::SeoUpdate);
            $subject = $suggestion->subject;

            if ($subject === null) {
                throw DomainValidationException::withErrors(['suggestion' => ['The product of this suggestion no longer exists. Reject it instead.']]);
            }

            $this->seo->apply($subject, $values);

            return $this->markReviewed($suggestion, SuggestionStatus::Approved, $reviewer, null, [
                ...$suggestion->payload,
                ...$values->toArray(),
                'as_suggested' => $suggestion->payload,
            ]);
        });
    }

    /**
     * Creates the building and its rooms (draft unless $publish) and marks the suggestion approved.
     *
     * @throws DomainValidationException
     */
    public function approveAccommodation(int $suggestionId, AccommodationSuggestionData $data, bool $publish, User $reviewer): AiSuggestion
    {
        return DB::transaction(function () use ($suggestionId, $data, $publish, $reviewer): AiSuggestion {
            $suggestion = $this->lockPending($suggestionId, SuggestionType::AccommodationCreate);
            $destinationId = $this->destinations->activeIdBySlug((string) $data->building['destination_slug']);

            if ($destinationId === null) {
                throw DomainValidationException::withErrors(['building.destination_slug' => ['Choose an active destination.']]);
            }

            $building = $this->buildings->create(BuildingData::fromArray([
                ...collect($data->building)->except(['destination_slug'])->all(),
                'destination_id' => $destinationId,
                'status' => ($publish ? PublishStatus::Published : PublishStatus::Draft)->value,
                'deposit_percent' => $this->settings->defaultDepositPercent(),
            ]));

            foreach ($data->rooms as $index => $room) {
                $this->rooms->create(RoomData::fromArray([
                    ...$room,
                    'building_id' => $building->id,
                    'pricing_mode' => RoomPricingMode::PerRoom->value,
                    'status' => RoomStatus::Active->value,
                    'sort' => $index,
                ]));
            }

            $suggestion->subject()->associate($building);

            return $this->markReviewed($suggestion, SuggestionStatus::Approved, $reviewer, null, [
                ...$data->toArray(),
                'published' => $publish,
                'as_suggested' => $suggestion->payload,
            ]);
        });
    }

    /**
     * @throws DomainValidationException when the suggestion was already decided.
     */
    public function reject(int $suggestionId, string $reason, User $reviewer): AiSuggestion
    {
        return DB::transaction(function () use ($suggestionId, $reason, $reviewer): AiSuggestion {
            $suggestion = $this->lockPending($suggestionId);

            return $this->markReviewed($suggestion, SuggestionStatus::Rejected, $reviewer, $reason);
        });
    }

    public static function subjectLabel(Model $subject): string
    {
        return match (true) {
            $subject instanceof Trip, $subject instanceof Package => (string) $subject->translate('title', 'en'),
            $subject instanceof Building => (string) $subject->translate('name', 'en'),
            $subject instanceof Room => trim($subject->building?->translate('name', 'en').' – '.$subject->translate('name', 'en'), ' –'),
            $subject instanceof TransferFixedRoute => trim($subject->hub?->translate('name', 'en').' → '.$subject->translate('destination_name', 'en').($subject->vehicleType ? ' ('.$subject->vehicleType->translate('name', 'en').')' : ''), ' →'),
            default => '#'.$subject->getKey(),
        };
    }

    /**
     * @throws DomainValidationException
     */
    private function lockPending(int $suggestionId, ?SuggestionType $type = null): AiSuggestion
    {
        $suggestion = $this->suggestions->findForReview($suggestionId, lock: true);

        if (! $suggestion->isPending()) {
            throw DomainValidationException::withErrors(['suggestion' => ["This suggestion was already {$suggestion->status->value}."]]);
        }

        if ($type !== null && $suggestion->type !== $type) {
            throw DomainValidationException::withErrors(['suggestion' => ['Wrong suggestion type.']]);
        }

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>|null  $payload  Applied values (approval) to keep with the decision.
     */
    private function markReviewed(AiSuggestion $suggestion, SuggestionStatus $status, User $reviewer, ?string $note, ?array $payload = null): AiSuggestion
    {
        $attributes = [
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ];

        if ($payload !== null) {
            $attributes['payload'] = $payload;
        }

        return $this->suggestions->update($suggestion, $attributes);
    }
}
