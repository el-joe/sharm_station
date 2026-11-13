<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainValidationException;
use App\Exceptions\GoogleReviewSyncException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GooglePlaceRequest;
use App\Http\Requests\Admin\GoogleReviewVisibilityRequest;
use App\Models\GoogleReview;
use App\Services\BuildingService;
use App\Services\GoogleReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Google reviews (CR-4, SRS 13.4): business Place ID, sync now, hide or show cached reviews.
 */
class GoogleReviewController extends Controller
{
    public function __construct(
        private readonly GoogleReviewService $reviews,
        private readonly BuildingService $buildings,
    ) {}

    public function index(Request $request): View
    {
        $businessPlaceId = $this->reviews->defaultPlaceId();
        $placeId = (string) ($request->query('place_id') ?: $businessPlaceId);

        return view('admin.google-reviews.index', [
            'businessPlaceId' => $businessPlaceId,
            'placeId' => $placeId,
            'places' => array_filter([$businessPlaceId => $businessPlaceId ? 'Business profile' : null]) + $this->buildings->placeOptions(),
            'summary' => $this->reviews->summary($placeId ?: null),
            'items' => $placeId !== '' ? $this->reviews->allFor($placeId) : collect(),
        ]);
    }

    public function updatePlace(GooglePlaceRequest $request): RedirectResponse
    {
        $this->reviews->setDefaultPlaceId($request->validated('place_id'));

        return redirect()->route('admin.google-reviews.index')->with('success', 'Business Place ID saved.');
    }

    public function sync(Request $request): JsonResponse|RedirectResponse
    {
        $placeId = $request->input('place_id') ?: null;

        try {
            $count = $this->reviews->sync($placeId);
        } catch (GoogleReviewSyncException $exception) {
            throw DomainValidationException::withErrors(['place_id' => [$exception->getMessage()]]);
        }

        $message = "Synced {$count} Google ".str('review')->plural($count).'.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'count' => $count])
            : back()->with('success', $message);
    }

    public function update(GoogleReviewVisibilityRequest $request, GoogleReview $review): JsonResponse
    {
        $review = $this->reviews->setHidden($review, $request->boolean('hidden'));

        return response()->json([
            'hidden' => $review->is_hidden,
            'message' => $review->is_hidden ? 'Review hidden from the site.' : 'Review visible on the site.',
        ]);
    }
}
