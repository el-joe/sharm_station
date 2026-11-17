<?php

namespace App\Mcp\Tools;

use App\DTOs\Ai\SeoValuesData;
use App\Enums\SeoSubjectType;
use App\Exceptions\DomainValidationException;
use App\Http\Requests\Admin\SeoSuggestionRequest;
use App\Mcp\Tools\Concerns\CreatesSuggestions;
use App\Services\AiSuggestionService;
use App\Services\SeoService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('suggest_seo')]
#[Description('Propose SEO for one product: meta title (max 70 chars), meta description (max 170), keywords, Open Graph title/description per locale ("en" and/or "it"), and optionally a new URL slug (trips, packages and buildings only). The current values are stored next to the proposal and it is saved as a PENDING suggestion: nothing changes on the website until an admin reviews, edits and approves it. Read the product first (get_trip, get_package, list_transfers, get_accommodation). Omit fields you do not want to change. Returns the suggestion id and status.')]
class SuggestSeoTool extends Tool
{
    use CreatesSuggestions;

    public function handle(Request $request, AiSuggestionService $suggestions, SeoService $seo): Response|ResponseFactory
    {
        $target = $request->validate([
            'subject_type' => ['required', Rule::enum(SeoSubjectType::class)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $locales = array_filter(['en' => $request->get('en'), 'it' => $request->get('it')], fn ($values): bool => is_array($values) && $values !== []);
        $values = array_map(fn (array $fields): array => [...$fields, 'keywords' => SeoValuesData::keywords($fields['keywords'] ?? null)], $locales);
        $validated = Validator::make(
            ['slug' => $request->get('slug'), 'locales' => $values],
            SeoSuggestionRequest::valueRules(),
            SeoSuggestionRequest::valueMessages(),
        )->validate();

        $type = SeoSubjectType::from($target['subject_type']);
        $subject = $seo->findSubject($type, (int) $target['subject_id']);

        if ($subject === null) {
            return Response::error("{$type->label()} {$target['subject_id']} not found.");
        }

        try {
            $suggestion = $suggestions->createSeo($subject, SeoValuesData::fromArray($validated), $target['notes'] ?? null, $this->creator($request), $this->token($request));
        } catch (DomainValidationException $exception) {
            return Response::error($exception->getMessage());
        }

        return $this->pendingResponse($suggestion);
    }

    public function schema(JsonSchema $schema): array
    {
        $locale = fn (string $language): mixed => $schema->object([
            'meta_title' => $schema->string()->max(70)->description("Meta title in {$language}, max 70 characters."),
            'meta_description' => $schema->string()->max(170)->description("Meta description in {$language}, max 170 characters."),
            'keywords' => $schema->array()->items($schema->string()->max(60))->max(20)->description('Search keywords (max 20).'),
            'og_title' => $schema->string()->max(95)->description('Social sharing (Open Graph) title, max 95 characters.'),
            'og_description' => $schema->string()->max(200)->description('Social sharing (Open Graph) description, max 200 characters.'),
        ])->description("SEO values in {$language}; omit a field to keep the current value.");

        return [
            'subject_type' => $schema->string()->enum(SeoSubjectType::values())->required()->description('trip, package, transfer_route (a fixed route id from list_transfers), building or room.'),
            'subject_id' => $schema->integer()->min(1)->required()->description('Id of the product.'),
            'en' => $locale('English'),
            'it' => $locale('Italian'),
            'slug' => $schema->string()->max(180)->pattern('^[a-z0-9]+(?:-[a-z0-9]+)*$')->description('Optional new URL slug (lowercase words separated by dashes). Only for trip, package and building; changing it changes the public URL.'),
            'notes' => $schema->string()->max(2000)->description('Short rationale for the reviewing admin.'),
        ];
    }
}
