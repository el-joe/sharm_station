<?php

namespace App\Console\Commands;

use App\Exceptions\GoogleReviewSyncException;
use App\Models\Building;
use App\Services\GoogleReviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviews:sync-google {placeId? : Sync a single Google Place ID}')]
#[Description('Fetch Google reviews for the business and buildings with a Place ID')]
class SyncGoogleReviews extends Command
{
    public function handle(GoogleReviewService $reviews): int
    {
        $placeIds = $this->argument('placeId') !== null
            ? [$this->argument('placeId')]
            : array_values(array_unique(array_filter([
                $reviews->defaultPlaceId(),
                ...Building::query()->whereNotNull('google_place_id')->pluck('google_place_id')->all(),
            ])));

        $failed = false;

        foreach ($placeIds as $placeId) {
            try {
                $count = $reviews->sync($placeId);
                $this->info("{$placeId}: {$count} reviews synced.");
            } catch (GoogleReviewSyncException $exception) {
                $failed = true;
                $this->error("{$placeId}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
