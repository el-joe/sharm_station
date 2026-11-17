<?php

namespace App\Mcp\Tools;

use App\DTOs\Ai\AccommodationSuggestionData;
use App\Enums\BuildingType;
use App\Enums\RoomType;
use App\Http\Requests\Admin\AccommodationSuggestionRequest;
use App\Mcp\Tools\Concerns\CreatesSuggestions;
use App\Services\AiSuggestionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_accommodation_suggestion')]
#[Description('Propose a NEW accommodation (building with its rooms) for the website. It is validated like the admin building and room forms and stored as a PENDING suggestion: nothing is published until an admin reviews, edits and approves it (it is then created as a draft unless the admin publishes it). Call list_accommodations first to avoid duplicates. Prices are EUR per night for the default nationality group; admins add group and seasonal prices after approval. Returns the suggestion id and status.')]
class CreateAccommodationSuggestionTool extends Tool
{
    use CreatesSuggestions;

    public function handle(Request $request, AiSuggestionService $suggestions): ResponseFactory
    {
        $payload = AccommodationSuggestionData::payloadFromToolArguments($request->all());
        $validated = Validator::make($payload, AccommodationSuggestionRequest::payloadRules(), AccommodationSuggestionRequest::payloadMessages())->validate();

        $suggestion = $suggestions->createAccommodation(AccommodationSuggestionData::fromArray($validated), $this->creator($request), $this->token($request));

        return $this->pendingResponse($suggestion);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name_en' => $schema->string()->max(160)->required()->description('Building name in English (also used for the URL slug).'),
            'name_it' => $schema->string()->max(160)->description('Building name in Italian, if different.'),
            'type' => $schema->string()->enum(BuildingType::values())->required()->description('hotel, apartment (apartment building), villa or hostel.'),
            'destination' => $schema->string()->required()->description('Slug of an existing active destination, e.g. "hurghada" or "sharm-el-sheikh" (see the destination of list_trips / list_accommodations items).'),
            'address' => $schema->string()->max(255)->description('Street address.'),
            'lat' => $schema->number()->min(-90)->max(90)->description('Latitude (decimal degrees).'),
            'lng' => $schema->number()->min(-180)->max(180)->description('Longitude (decimal degrees).'),
            'stars' => $schema->integer()->min(1)->max(5)->description('Official star rating 1-5.'),
            'description_en' => $schema->string()->max(20000)->description('Description in English: factual, no invented claims.'),
            'description_it' => $schema->string()->max(20000)->description('Description in Italian.'),
            'amenities' => $schema->array()->items($schema->string()->max(60))->max(40)->description('Amenity codes or labels, e.g. ["wifi","pool","beach","parking","breakfast","airport_shuttle","spa","gym","kids_club"].'),
            'check_in_from' => $schema->string()->pattern('^\d{2}:\d{2}$')->description('Check-in from, 24h "HH:MM".'),
            'check_out_until' => $schema->string()->pattern('^\d{2}:\d{2}$')->description('Check-out until, 24h "HH:MM".'),
            'taxes_included' => $schema->boolean()->description('Whether local taxes are included in the room price (default true).'),
            'tax_per_night' => $schema->number()->min(0)->description('Tax per night in EUR; required when taxes_included is false.'),
            'phone' => $schema->string()->max(40)->description('Property phone.'),
            'email' => $schema->string()->format('email')->description('Property e-mail.'),
            'notes' => $schema->string()->max(2000)->description('Notes for the reviewing admin: sources, assumptions, what to double-check.'),
            'rooms' => $schema->array()->max(30)->items($schema->object([
                'name_en' => $schema->string()->max(160)->required()->description('Room name in English, e.g. "Deluxe sea view room".'),
                'name_it' => $schema->string()->max(160),
                'room_type' => $schema->string()->enum(RoomType::values())->required()->description('single, double, twin, triple, family or suite.'),
                'max_adults' => $schema->integer()->min(1)->max(20)->required(),
                'max_children' => $schema->integer()->min(0)->max(20)->description('Default 0.'),
                'max_total' => $schema->integer()->min(1)->max(30)->required()->description('Maximum guests; at least max_adults.'),
                'beds_en' => $schema->string()->max(120)->description('Beds, e.g. "1 king bed or 2 single beds".'),
                'beds_it' => $schema->string()->max(120),
                'size_m2' => $schema->integer()->min(1)->max(2000),
                'base_price_per_night' => $schema->number()->min(0)->required()->description('Base price per room per night in EUR.'),
                'min_nights' => $schema->integer()->min(1)->max(365)->description('Default 1.'),
                'max_nights' => $schema->integer()->min(1)->max(365),
                'amenities' => $schema->array()->items($schema->string()->max(60))->max(40),
                'description_en' => $schema->string()->max(10000),
                'description_it' => $schema->string()->max(10000),
            ]))->required()->description('Room types of the building (one entry per room type).'),
        ];
    }
}
