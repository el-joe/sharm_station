<?php

use App\Enums\CommissionCategory;
use App\Enums\PriceRule;
use App\Models\Booking;
use App\Models\CommissionSetting;
use App\Models\Partner;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\User;
use App\Services\Pricing\CommissionResolver;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->partner = Partner::factory()->create(['company' => 'Nile Tours Srl']);
    $this->trip = snorkellingTrip(['base_price' => 45, 'base_child_price' => 30]);
});

function partnerTripPayload(array $overrides = []): array
{
    return [
        'product_type' => 'trip',
        'slug' => 'ras-mohammed-snorkelling',
        'nationality_code' => 'EG',
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 1,
        ...$overrides,
    ];
}

describe('commission resolution', function () {
    test('partner override → global setting → 0 %, and accommodation is always 0', function () {
        $resolver = app(CommissionResolver::class);
        partnerOverride($this->partner, CommissionCategory::MultiDayTrips, 12);

        $override = $resolver->resolve($this->partner->fresh(), CommissionCategory::MultiDayTrips);
        $global = $resolver->resolve($this->partner->fresh(), CommissionCategory::SingleDayTrips);
        $accommodation = $resolver->resolve($this->partner->fresh(), null);

        CommissionSetting::query()->whereKey('transfers')->delete();
        $resolver->flush();
        $none = $resolver->resolve($this->partner->fresh(), CommissionCategory::Transfers);

        expect([$override->percent, $override->source])->toBe([12.0, PriceRule::PartnerOverride])
            ->and([$global->percent, $global->source])->toBe([15.0, PriceRule::GlobalSetting])
            ->and([$accommodation->percent, $accommodation->source])->toBe([0.0, PriceRule::None])
            ->and([$none->percent, $none->source])->toBe([0.0, PriceRule::None]);
    });
});

describe('access', function () {
    test('only active partners can use the partner booking API', function () {
        $this->getJson('/api/v1/partner/catalogue')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/partner/catalogue')->assertForbidden();
        $this->postJson('/api/v1/partner/quotes', partnerTripPayload())->assertForbidden();

        Sanctum::actingAs(Partner::factory()->pending()->create()->user);
        $this->postJson('/api/v1/partner/bookings', partnerTripPayload())->assertForbidden();
    });
});

describe('partner panel booking flow', function () {
    beforeEach(fn () => Sanctum::actingAs($this->partner->user));

    test('the catalogue lists customer price, partner price and commission for the client nationality', function () {
        ProductPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $this->trip->id, 'nationality_group_id' => nationalityGroup('egyptian')->id, 'adult_price' => 20, 'child_price' => null]);
        Room::factory()->create(['base_price' => 80, 'from_price' => 80]);

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=IT')
            ->assertOk()
            ->assertJsonPath('nationality.group.code', 'europe')
            ->assertJsonPath('data.0.title', 'Ras Mohammed snorkelling')
            ->assertJsonPath('data.0.product_type', 'trip')
            ->assertJsonPath('data.0.customer_price.amount_eur', 45)
            ->assertJsonPath('data.0.partner_price.amount_eur', 38.25)
            ->assertJsonPath('data.0.commission.percent', 15)
            ->assertJsonPath('data.0.commission.source', 'global_setting');

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=EG&q=snorkelling')
            ->assertOk()
            ->assertJsonPath('data.0.customer_price.amount_eur', 20)
            ->assertJsonPath('data.0.partner_price.amount_eur', 17);

        $this->getJson('/api/v1/partner/catalogue?service=accommodation')
            ->assertOk()
            ->assertJsonPath('data.0.customer_price.amount_eur', 80)
            ->assertJsonPath('data.0.partner_price.amount_eur', 80)
            ->assertJsonPath('data.0.commission.source', 'none');

        $this->getJson('/api/v1/partner/catalogue?service=unknown')->assertUnprocessable()->assertJsonValidationErrors(['service']);
    });

    test('catalogue: "From" price without a date, the season price and name with a date', function () {
        $this->trip->update(['private_enabled' => false]); // group prices only: one headline field
        $egyptian = nationalityGroup('egyptian');
        ProductPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $this->trip->id, 'nationality_group_id' => $egyptian->id, 'adult_price' => 20, 'child_price' => null]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->between('2026-11-01', '2026-12-19', 'High season')->create(['adult_price' => 60]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->forGroup($egyptian)->between('2026-11-01', '2026-12-19', 'High season EG')->create(['adult_price' => 25]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->between('2027-01-07', '2027-03-31', 'Low season')->create(['adult_price' => 35]);

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=EG')
            ->assertOk()
            ->assertJsonPath('data.0.price_basis', 'from')
            ->assertJsonPath('data.0.season', null)
            ->assertJsonPath('data.0.customer_price.amount_eur', 20);

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=EG&date=2026-11-20')
            ->assertOk()
            ->assertJsonPath('data.0.price_basis', 'date')
            ->assertJsonPath('data.0.season', 'High season EG')
            ->assertJsonPath('data.0.customer_price.amount_eur', 25)
            ->assertJsonPath('data.0.partner_price.amount_eur', 21.25);

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=IT&date=2027-02-01')
            ->assertOk()
            ->assertJsonPath('data.0.season', 'Low season')
            ->assertJsonPath('data.0.customer_price.amount_eur', 35);

        $this->getJson('/api/v1/partner/catalogue?service=single_day_trips&nationality=IT&date=2027-05-01')
            ->assertOk()
            ->assertJsonPath('data.0.season', null)
            ->assertJsonPath('data.0.customer_price.amount_eur', 45);
    });

    test('the price list has a base row and one row per season of the client group', function () {
        $this->trip->update(['private_enabled' => false]); // group prices only: one headline field
        $egyptian = nationalityGroup('egyptian');
        ProductPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $this->trip->id, 'nationality_group_id' => $egyptian->id, 'adult_price' => 20, 'child_price' => null]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->between('2026-11-01', '2026-12-19', 'High season')->create(['adult_price' => 60]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->forGroup($egyptian)->between('2026-11-01', '2026-12-19', 'High season EG')->create(['adult_price' => 25]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->between('2027-01-07', '2027-03-31', 'Low season')->create(['adult_price' => 35]);

        // EG: the group's own high season hides the all-groups one.
        $this->getJson('/api/v1/partner/price-list?service=single_day_trips&nationality=EG')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.row_type', 'base')
            ->assertJsonPath('data.0.season', null)
            ->assertJsonPath('data.0.retail_price.amount_eur', 20)
            ->assertJsonPath('data.0.partner_price.amount_eur', 17)
            ->assertJsonPath('data.1.row_type', 'season')
            ->assertJsonPath('data.1.season', 'High season EG')
            ->assertJsonPath('data.1.starts_on', '2026-11-01')
            ->assertJsonPath('data.1.ends_on', '2026-12-19')
            ->assertJsonPath('data.1.retail_price.amount_eur', 25)
            ->assertJsonPath('data.1.partner_price.amount_eur', 21.25)
            ->assertJsonPath('data.2.season', 'Low season')
            ->assertJsonPath('data.2.retail_price.amount_eur', 35)
            ->assertJsonPath('data.2.nationality_group.code', 'egyptian');

        $this->getJson('/api/v1/partner/price-list?service=single_day_trips&nationality=IT')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.retail_price.amount_eur', 45)
            ->assertJsonPath('data.1.season', 'High season')
            ->assertJsonPath('data.1.retail_price.amount_eur', 60)
            ->assertJsonPath('data.2.season', 'Low season');
    });

    test('a partner quote returns customer lines, commission and partner totals (€45 − 15 % = €38.25)', function () {
        $this->postJson('/api/v1/partner/quotes', partnerTripPayload(['nationality_code' => 'IT']))
            ->assertOk()
            ->assertJsonPath('data.retail_total', 45)
            ->assertJsonPath('data.partner_total', 38.25)
            ->assertJsonPath('data.commission_amount', 6.75)
            ->assertJsonPath('data.commission_percent', 15)
            ->assertJsonPath('data.commission_source', 'global_setting')
            ->assertJsonPath('data.total', 38.25)
            ->assertJsonPath('data.lines.0.unit_price', 45)
            ->assertJsonPath('data.lines.0.partner_unit_price', 38.25)
            ->assertJsonPath('data.nationality.code', 'IT')
            ->assertJsonPath('data.nationality.group.code', 'europe');
    });

    test('a partner override changes the partner quote', function () {
        partnerOverride($this->partner, CommissionCategory::SingleDayTrips, 20);

        $this->postJson('/api/v1/partner/quotes', partnerTripPayload(['adults' => 2]))
            ->assertOk()
            ->assertJsonPath('data.retail_total', 90)
            ->assertJsonPath('data.partner_total', 72)
            ->assertJsonPath('data.commission_source', 'partner_override');
    });

    test('quotes validate the product and the client nationality', function () {
        $this->postJson('/api/v1/partner/quotes', ['product_type' => 'trip'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'nationality_code', 'booking_type', 'travel_date', 'adults']);

        $this->postJson('/api/v1/partner/quotes', partnerTripPayload(['slug' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    test('a partner booking records the partner as booker and the client nationality', function () {
        $response = $this->postJson('/api/v1/partner/bookings', partnerTripPayload([
            'nationality_code' => 'EG',
            'lead' => ['name' => 'Ahmed Hassan', 'email' => 'ahmed@example.com'],
            'travelers' => [['first_name' => 'Ahmed', 'last_name' => 'Hassan', 'type' => 'adult']],
            'payment_method' => 'bank_transfer',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.totals.retail_total', 45)
            ->assertJsonPath('data.totals.partner_total', 38.25)
            ->assertJsonPath('data.totals.commission_percent', 15)
            ->assertJsonPath('data.totals.commission_source', 'global_setting')
            ->assertJsonPath('data.nationality.code', 'EG')
            ->assertJsonPath('data.nationality.group.code', 'egyptian')
            ->assertJsonPath('meta.payment_method', 'bank_transfer');

        $booking = Booking::query()->where('reference', $response->json('data.reference'))->firstOrFail();

        expect($booking->partner_id)->toBe($this->partner->id)
            ->and($booking->user_id)->toBe($this->partner->user_id)
            ->and($booking->lead_name)->toBe('Ahmed Hassan')
            ->and($booking->nationality_code)->toBe('EG')
            ->and($booking->nationality_group_id)->toBe(nationalityGroup('egyptian')->id)
            ->and((float) $booking->commission_percent)->toBe(15.0)
            ->and((float) $booking->total)->toBe(38.25)
            ->and($booking->price_rule)->toBe(PriceRule::GlobalSetting);

        $this->getJson('/api/v1/partner/bookings')->assertOk()->assertJsonPath('data.0.totals.partner_total', 38.25);

        app('auth')->forgetGuards();
        $client = $this->withHeaders(['Authorization' => ''])->getJson("/api/v1/bookings/{$booking->reference}?token={$booking->access_token}")->assertOk();
        expect($client->json('data.totals'))->toBe(['retail_total' => 45, 'base_currency' => 'EUR', 'prices_hidden' => true]);
    });

    test('the end client of a partner booking cannot infer the partner price from payments or payment options', function () {
        $response = $this->postJson('/api/v1/partner/bookings', partnerTripPayload([
            'nationality_code' => 'IT',
            'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com'],
            'travelers' => [['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult']],
        ]))->assertCreated();
        $booking = Booking::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        $booking->payments()->create(['type' => 'balance', 'status' => 'succeeded', 'amount' => $booking->total, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => $booking->total, 'paid_at' => now()]);

        app('auth')->forgetGuards();
        $client = $this->withHeaders(['Authorization' => ''])->getJson("/api/v1/bookings/{$booking->reference}?token={$booking->access_token}")->assertOk();

        expect($client->json('data.payments'))->toBe([])
            ->and($client->json('data'))->not->toHaveKey('payment_options')
            ->and($client->json('data.payment_summary'))->toBeNull()
            ->and($client->getContent())->not->toContain('"38.25"')->not->toContain('38.25');
    });

    test('partner room bookings pay the customer price', function () {
        $room = Room::factory()->create(['base_price' => 80, 'min_nights' => 1]);

        $this->postJson('/api/v1/partner/quotes', [
            'product_type' => 'room', 'id' => $room->id, 'nationality_code' => 'IT',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'adults' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.retail_total', 160)
            ->assertJsonPath('data.partner_total', 160)
            ->assertJsonPath('data.commission_source', 'none');
    });
});

describe('public endpoints with a partner token', function () {
    test('never expose partner prices', function () {
        buggyTrip();
        Sanctum::actingAs($this->partner->user);

        foreach (['/api/v1/trips', '/api/v1/trips/ras-mohammed-snorkelling', '/api/v1/trips/desert-buggy-safari', '/api/v1/home'] as $url) {
            expect($this->getJson($url)->assertOk()->getContent())->not->toContain('partner_price')->not->toContain('partner_from_price');
        }

        expect($this->postJson('/api/v1/trips/ras-mohammed-snorkelling/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 1])
            ->assertOk()->assertJsonPath('data.total', 45)->getContent())->not->toContain('partner_total');
    });
});
