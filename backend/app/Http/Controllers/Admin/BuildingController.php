<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BuildingDataTable;
use App\DTOs\Building\BuildingData;
use App\Enums\BuildingType;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuildingRequest;
use App\Models\Building;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Services\BuildingService;
use App\Services\GoogleReviewService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BuildingController extends Controller
{
    /**
     * Managed amenities list (SRS 6.1); admins can add new tags in the select.
     *
     * @var array<string, string>
     */
    public const AMENITIES = [
        'wifi' => 'Wi-Fi', 'pool' => 'Pool', 'parking' => 'Parking', 'breakfast' => 'Breakfast', 'reception_24h' => 'Reception 24/7',
        'airport_shuttle' => 'Airport shuttle', 'restaurant' => 'Restaurant', 'beach' => 'Private beach', 'gym' => 'Gym', 'spa' => 'Spa',
        'air_conditioning' => 'Air conditioning', 'kitchen' => 'Kitchen', 'bar' => 'Bar', 'kids_club' => 'Kids club',
    ];

    public function __construct(
        private readonly BuildingService $buildings,
        private readonly DestinationRepositoryInterface $destinations,
        private readonly GoogleReviewService $reviews,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.buildings.index', ['destinations' => $this->destinations->selectOptions()]);
    }

    public function data(Request $request, BuildingDataTable $table): JsonResponse
    {
        return $table->json($request->integer('destination_id') ?: null);
    }

    public function create(): View
    {
        return view('admin.buildings.edit', $this->formData(new Building([
            'type' => BuildingType::Hotel,
            'status' => PublishStatus::Draft,
            'taxes_included' => true,
            'deposit_percent' => $this->settings->defaultDepositPercent(),
        ])));
    }

    public function store(BuildingRequest $request): RedirectResponse
    {
        $building = $this->buildings->create(BuildingData::fromArray($request->payload()));

        return redirect()->route('admin.buildings.edit', $building)->with('success', 'Building created.');
    }

    public function edit(Building $building): View
    {
        return view('admin.buildings.edit', $this->formData($building->loadCount('rooms')));
    }

    public function update(BuildingRequest $request, Building $building): RedirectResponse
    {
        $this->buildings->update($building, BuildingData::fromArray($request->payload()));

        return redirect()->route('admin.buildings.edit', $building)->with('success', 'Building saved.');
    }

    public function destroy(Building $building): RedirectResponse
    {
        $this->buildings->delete($building);

        return redirect()->route('admin.buildings.index')->with('success', 'Building deleted.');
    }

    /**
     * "Sync now" for the building's Google Place ID (JSON for the button, redirect otherwise).
     */
    public function syncReviews(Request $request, Building $building): JsonResponse|RedirectResponse
    {
        $count = $this->buildings->syncReviews($building);
        $message = "Synced {$count} Google ".str('review')->plural($count).'.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'count' => $count])
            : back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Building $building): array
    {
        return [
            'building' => $building,
            'destinations' => $this->destinations->selectOptions(),
            'amenities' => self::AMENITIES,
            'rating' => $this->reviews->rating($building->google_place_id),
            'canPrice' => Gate::allows('manage-prices'),
        ];
    }
}
