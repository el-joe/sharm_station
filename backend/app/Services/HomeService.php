<?php

namespace App\Services;

use App\Enums\TripType;
use App\Models\Trip;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * Aggregates the content blocks of the home page.
 */
class HomeService
{
    public function __construct(
        private readonly TripService $trips,
        private readonly RoomService $rooms,
        private readonly DestinationService $destinations,
        private readonly GoogleReviewService $reviews,
        private readonly PackageRepositoryInterface $packages,
        private readonly SettingRepositoryInterface $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $destinations = $this->destinations->active();
        $reviews = $this->reviews->summary(limit: 6);

        return [
            'stats' => [
                'trips' => Trip::query()->published()->count(),
                'destinations' => $destinations->count(),
                'travellers' => (int) $this->settings->get('site.travellers_served', 0),
                'google_rating' => $reviews['rating'],
                'google_reviews' => $reviews['total'],
            ],
            'featured_trips' => $this->trips->featured([TripType::SingleDay, TripType::MultiDay], 8),
            'nile_trips' => $this->trips->featured([TripType::Nile], 4),
            'packages' => $this->packages->featured(4),
            'rooms' => $this->rooms->featured(6),
            'destinations' => $destinations,
            'reviews' => $reviews,
        ];
    }
}
