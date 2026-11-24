<?php

namespace App\Services;

use App\Enums\TripType;
use App\Http\Middleware\SetLocaleAndCurrency;
use App\Models\Currency;
use App\Models\Destination;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;

/**
 * Data for the site header and footer.
 */
class MetaService
{
    /**
     * Settings keys that are safe to expose publicly.
     */
    public const PUBLIC_SETTINGS = ['site.name', 'site.email', 'site.phone', 'site.whatsapp', 'site.address', 'site.social', 'site.lat', 'site.lng'];

    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly DestinationService $destinations,
        private readonly GoogleReviewService $reviews,
        private readonly SettingRepositoryInterface $settings,
        private readonly TripRepositoryInterface $trips,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $placeId = $this->reviews->defaultPlaceId();
        $settings = $this->settings->many(self::PUBLIC_SETTINGS);
        $tripCounts = $this->trips->query()->published()->selectRaw('destination_id, count(*) as aggregate')->groupBy('destination_id')->pluck('aggregate', 'destination_id');
        $nested = [];

        foreach ($settings as $key => $value) {
            data_set($nested, $key, $value);
        }

        return [
            'locale' => app()->getLocale(),
            'locales' => SetLocaleAndCurrency::LOCALES,
            'currency' => $this->currencies->current(),
            'currencies' => $this->currencies->enabled()->map(fn (Currency $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'rate' => $this->currencies->rate($currency->code),
            ])->values()->all(),
            'destinations' => $this->destinations->active()->map(fn (Destination $destination): array => [
                'name' => $destination->name,
                'slug' => $destination->slug,
                'image' => $destination->image,
                'trips_count' => (int) ($tripCounts[$destination->id] ?? 0),
            ])->values()->all(),
            'trip_types' => collect(TripType::cases())->map(fn (TripType $type): array => ['value' => $type->value, 'label' => $type->label()])->all(),
            'categories' => $this->trips->query()->published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all(),
            'settings' => $settings,
            'site' => $nested['site'] ?? (object) [],
            'whatsapp_digits' => isset($settings['site.whatsapp']) ? preg_replace('/\D+/', '', (string) $settings['site.whatsapp']) : null,
            'google' => $placeId ? $this->reviews->rating($placeId) : null,
            'contact' => [
                'address' => $settings['site.address'] ?? null,
                'lat' => isset($settings['site.lat']) ? (float) $settings['site.lat'] : null,
                'lng' => isset($settings['site.lng']) ? (float) $settings['site.lng'] : null,
                'phone' => $settings['site.phone'] ?? null,
                'email' => $settings['site.email'] ?? null,
                'whatsapp' => $settings['site.whatsapp'] ?? null,
            ],
        ];
    }
}
