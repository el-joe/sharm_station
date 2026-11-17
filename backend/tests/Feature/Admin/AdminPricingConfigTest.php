<?php

use App\Enums\CommissionCategory;
use App\Enums\UserRole;
use App\Models\CommissionSetting;
use App\Models\Country;
use App\Models\NationalityGroup;
use App\Models\Partner;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\TransferFixedRoute;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

describe('Settings → Commissions', function () {
    test('the page renders the five types and accommodation without commission', function () {
        $partner = Partner::factory()->create(['company' => 'Red Sea Travel GmbH']);
        partnerOverride($partner, CommissionCategory::Transfers, 10);

        $this->actingAs($this->admin)->get(route('admin.commissions.edit'))
            ->assertOk()
            ->assertSee('Partner commissions')
            ->assertSee('Single-day trips')->assertSee('Multi-day trips')->assertSee('Nile trips')->assertSee('Packages')->assertSee('Transfers')
            ->assertSee('No commission')
            ->assertSee('Red Sea Travel GmbH');
    });

    test('global commissions are saved and validated', function () {
        $values = ['single_day_trips' => 12, 'multi_day_trips' => 11, 'nile_trips' => 9.5, 'packages' => 7, 'transfers' => 0];

        $this->actingAs($this->admin)->put(route('admin.commissions.update'), ['commissions' => $values])
            ->assertRedirect(route('admin.commissions.edit'))
            ->assertSessionHas('success');

        expect(CommissionSetting::query()->pluck('percent', 'category')->map(fn ($percent) => (float) $percent)->all())
            ->toEqual(['multi_day_trips' => 11.0, 'nile_trips' => 9.5, 'packages' => 7.0, 'single_day_trips' => 12.0, 'transfers' => 0.0]);

        $this->actingAs($this->admin)->put(route('admin.commissions.update'), ['commissions' => [...$values, 'packages' => 120, 'transfers' => '']])
            ->assertSessionHasErrors(['commissions.packages', 'commissions.transfers']);
    });

    test('editors cannot change commissions', function () {
        $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.commissions.edit'))->assertForbidden();
    });
});

describe('Partner profile → Commissions', function () {
    test('overrides are saved, cleared and the effective rate is shown', function () {
        $partner = Partner::factory()->create(['company' => 'Nile Tours Srl']);

        $this->actingAs($this->admin)->get(route('admin.partners.show', $partner))
            ->assertOk()->assertSee('Commission per type')->assertSee('Use standard')->assertSee('Standard');

        $this->actingAs($this->admin)->put(route('admin.partners.commissions.override', $partner), ['commissions' => [
            'single_day_trips' => '', 'multi_day_trips' => '12', 'nile_trips' => '', 'packages' => '', 'transfers' => '',
        ]])->assertRedirect(route('admin.partners.show', $partner).'#tab-commissions');

        expect($partner->fresh()->load('categoryCommissions')->commissionFor(CommissionCategory::MultiDayTrips))->toBe(12.0)
            ->and($partner->fresh()->categoryCommissions)->toHaveCount(1);

        $this->actingAs($this->admin)->get(route('admin.partners.show', $partner))
            ->assertSee('Override')
            ->assertSee('Multi-day trips uses this partner’s override (12 %) instead of the standard 10 %', false);

        $this->actingAs($this->admin)->put(route('admin.partners.commissions.override', $partner), ['commissions' => ['multi_day_trips' => '']]);
        expect($partner->fresh()->categoryCommissions)->toHaveCount(0);

        $this->actingAs($this->admin)->put(route('admin.partners.commissions.override', $partner), ['commissions' => ['packages' => 101]])
            ->assertSessionHasErrors(['commissions.packages' => 'A commission cannot exceed 100%.']);
    });
});

describe('Nationality groups', function () {
    test('the page lists groups, the default group and countries without a group', function () {
        $this->actingAs($this->admin)->get(route('admin.nationality-groups.index'))
            ->assertOk()
            ->assertSee('Egyptian')->assertSee('Arab countries')->assertSee('Europe')->assertSee('Rest of world')
            ->assertSee('Default')
            ->assertSee('countries without a group')
            ->assertSee('Edit group: Egyptian');

        $this->actingAs($this->admin)->get(route('admin.nationality-groups.index', ['new' => 1]))->assertOk()->assertSee('New group');
    });

    test('a group is created with countries moved from other groups', function () {
        $this->actingAs($this->admin)->post(route('admin.nationality-groups.store'), [
            'name' => ['en' => 'Iberia', 'it' => 'Iberia'], 'sort' => 5, 'is_active' => '1', 'is_default' => '0',
            'countries' => ['ES', 'PT', 'AD'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $group = NationalityGroup::query()->where('code', 'iberia')->firstOrFail();

        expect(Country::query()->where('nationality_group_id', $group->id)->pluck('code')->sort()->values()->all())->toBe(['AD', 'ES', 'PT'])
            ->and(Country::query()->find('IT')->nationality_group_id)->toBe(nationalityGroup('europe')->id);
    });

    test('updating a group syncs its countries and can make it the default', function () {
        $arab = nationalityGroup('arab_countries');

        $this->actingAs($this->admin)->put(route('admin.nationality-groups.update', $arab), [
            'name' => ['en' => 'Arab countries'], 'sort' => 2, 'is_active' => '1', 'is_default' => '1', 'countries' => ['SA', 'AE'],
        ])->assertSessionHasNoErrors();

        expect($arab->fresh()->is_default)->toBeTrue()
            ->and(nationalityGroup('rest_of_world')->is_default)->toBeFalse()
            ->and(NationalityGroup::query()->where('is_default', true)->count())->toBe(1)
            ->and(Country::query()->where('nationality_group_id', $arab->id)->count())->toBe(2)
            ->and(Country::query()->find('KW')->nationality_group_id)->toBeNull();
    });

    test('the default group cannot lose its flag or be deleted; other groups can be deleted', function () {
        $default = nationalityGroup('rest_of_world');

        $this->actingAs($this->admin)->put(route('admin.nationality-groups.update', $default), [
            'name' => ['en' => 'Rest of world'], 'is_default' => '0', 'is_active' => '1',
        ])->assertSessionHasErrors('is_default');

        $this->actingAs($this->admin)->delete(route('admin.nationality-groups.destroy', $default))->assertSessionHasErrors('group');

        $europe = nationalityGroup('europe');
        $this->actingAs($this->admin)->delete(route('admin.nationality-groups.destroy', $europe))->assertRedirect(route('admin.nationality-groups.index'));

        expect(NationalityGroup::query()->find($europe->id))->toBeNull()
            ->and(Country::query()->find('IT')->nationality_group_id)->toBeNull();
    });
});

describe('Price matrix tab', function () {
    test('the trip, room and fixed route editors render the price matrix with group columns and no partner fields', function () {
        $trip = snorkellingTrip();
        $room = Room::factory()->create();
        $route = TransferFixedRoute::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.trips.edit', $trip))
            ->assertOk()
            ->assertSee('Price matrix')
            ->assertSee('Base price per nationality group')
            ->assertSee('Nationality group')
            ->assertSee('All groups')
            ->assertSee('Egyptian')
            ->assertSee('Private 1–2 pax')
            ->assertDontSee('Partner price (optional)');

        $this->actingAs($this->admin)->get(route('admin.rooms.edit', $room))->assertOk()->assertSee('Base price per nationality group')->assertSee('Per night');
        $this->actingAs($this->admin)->get(route('admin.transfers.routes.edit', $route))->assertOk()->assertSee('Price per vehicle')->assertDontSee('Partner price');
    });

    test('base prices per group and seasons per group are saved together', function () {
        $trip = snorkellingTrip();
        $tier = $trip->privateTiers->first();
        $egyptian = nationalityGroup('egyptian');
        $europe = nationalityGroup('europe');

        $this->actingAs($this->admin)->put(route('admin.trips.seasons.update', $trip), [
            'base' => [
                $egyptian->id => ['adult_price' => '20', 'child_price' => '10', 'tier_prices' => [$tier->id => '30']],
                $europe->id => ['adult_price' => '', 'child_price' => ''],
            ],
            'seasons' => [
                ['name' => ['en' => 'Summer'], 'nationality_group_id' => $egyptian->id, 'starts_on' => '2027-07-01', 'ends_on' => '2027-08-31', 'is_active' => '1', 'recurring_yearly' => '1', 'adult_price' => '25'],
                ['name' => ['en' => 'Summer'], 'nationality_group_id' => '', 'starts_on' => '2027-07-01', 'ends_on' => '2027-08-31', 'is_active' => '1', 'recurring_yearly' => '0', 'adult_price' => '50'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.trips.edit', $trip).'#tab-prices');

        $base = ProductPrice::query()->whereMorphedTo('priceable', $trip)->get();
        expect($base)->toHaveCount(1)
            ->and((float) $base[0]->adult_price)->toBe(20.0)
            ->and($base[0]->tierPriceFor($tier->id))->toBe(30.0)
            ->and(SeasonPrice::query()->whereMorphedTo('priceable', $trip)->pluck('nationality_group_id')->all())->toEqualCanonicalizing([$egyptian->id, null]);

        $this->actingAs($this->admin)->get(route('admin.trips.edit', $trip))->assertOk()->assertSee('value="20.00"', false);
    });

    test('an overlap within one group is rejected inline and nothing is saved', function () {
        $trip = snorkellingTrip();
        $europe = nationalityGroup('europe');

        $this->actingAs($this->admin)->from(route('admin.trips.edit', $trip))->put(route('admin.trips.seasons.update', $trip), [
            'base' => [$europe->id => ['adult_price' => '40']],
            'seasons' => [
                ['name' => ['en' => 'Spring'], 'nationality_group_id' => $europe->id, 'starts_on' => '2027-04-01', 'ends_on' => '2027-04-30', 'is_active' => '1', 'adult_price' => '50'],
                ['name' => ['en' => 'Easter'], 'nationality_group_id' => $europe->id, 'starts_on' => '2027-03-28', 'ends_on' => '2027-04-12', 'is_active' => '1', 'adult_price' => '52'],
            ],
        ])->assertRedirect(route('admin.trips.edit', $trip))
            ->assertSessionHasErrors(['seasons.1.starts_on' => '“Easter” (Europe) overlaps “Spring” on 1–12 Apr 2027.']);

        expect(SeasonPrice::query()->count())->toBe(0)
            ->and(ProductPrice::query()->count())->toBe(0);
    });

    test('a fixed route price matrix is saved', function () {
        $route = TransferFixedRoute::factory()->create(['price' => 35]);

        $this->actingAs($this->admin)->put(route('admin.transfers.routes.seasons.update', $route), [
            'base' => [nationalityGroup('egyptian')->id => ['price' => '20']],
            'seasons' => [],
        ])->assertRedirect(route('admin.transfers.routes.edit', $route).'#price-matrix');

        expect((float) ProductPrice::query()->whereMorphedTo('priceable', $route)->value('price'))->toBe(20.0);
    });
});
