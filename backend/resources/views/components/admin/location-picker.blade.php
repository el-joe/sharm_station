@props([
    'latName' => 'lat',
    'lngName' => 'lng',
    'addressName' => null,
    'lat' => null,
    'lng' => null,
    'address' => null,
    'latLabel' => 'Latitude',
    'lngLabel' => 'Longitude',
    'addressLabel' => 'Address',
    'addressMaxlength' => 255,
    'searchLabel' => 'Search a place',
    'span' => 'md:col-span-6',
])
@php
    $idFor = fn (string $name): string => 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $keyFor = fn (string $name): string => str_replace(['[', ']'], ['.', ''], $name);
    $mapsKey = config('services.google.maps_browser_key');
    $latId = $idFor($latName);
    $lngId = $idFor($lngName);
    $currentLat = old($keyFor($latName), $lat);
    $currentLng = old($keyFor($lngName), $lng);
    $hasPoint = is_numeric($currentLat) && is_numeric($currentLng);
    $previewUrl = $hasPoint ? 'https://www.google.com/maps?q='.((float) $currentLat).','.((float) $currentLng) : 'https://www.google.com/maps';
    $pickerId = $latId.'-picker';
@endphp
<div data-location-picker
     data-lat-input="#{{ $latId }}" data-lng-input="#{{ $lngId }}"
     @if ($addressName) data-address-input="#{{ $idFor($addressName) }}" @endif
     @if (filled($mapsKey)) data-maps-key="{{ $mapsKey }}" @endif
     data-default-lat="{{ \Database\Seeders\OfficeLocationSeeder::LAT }}" data-default-lng="{{ \Database\Seeders\OfficeLocationSeeder::LNG }}"
     @class([$span, 'grid grid-cols-1 gap-5 md:grid-cols-6'])>
    @if ($addressName)
        <x-admin.input :name="$addressName" :label="$addressLabel" :value="$address" :maxlength="$addressMaxlength" span="md:col-span-6" />
    @endif
    <x-admin.input :name="$latName" :label="$latLabel" type="number" step="0.0000001" min="-90" max="90" :value="$lat" inputmode="decimal" span="md:col-span-3" />
    <x-admin.input :name="$lngName" :label="$lngLabel" type="number" step="0.0000001" min="-180" max="180" :value="$lng" inputmode="decimal" span="md:col-span-3" />

    @if (filled($mapsKey))
        <div class="space-y-2 md:col-span-6" data-location-map-wrap>
            <p id="{{ $pickerId }}-search-label" class="form-label">{{ $searchLabel }}</p>
            <div data-location-search data-label="{{ $searchLabel }}" aria-labelledby="{{ $pickerId }}-search-label"></div>
            <div data-location-map class="h-72 w-full overflow-hidden rounded-control border border-ink-300/60 bg-ink-100" role="region" aria-label="Map: click or drag the marker to set the location"></div>
            <p class="form-hint" data-location-status>Click the map or drag the marker to set the coordinates.</p>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm md:col-span-6">
        <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" data-location-preview class="inline-flex items-center gap-1 font-medium text-primary-700 underline" @unless ($hasPoint) hidden @endunless>
            Preview on Google Maps<span class="sr-only"> (opens in a new tab)</span>
        </a>
        @unless (filled($mapsKey))
            <span class="text-ink-500">Add GOOGLE_MAPS_BROWSER_KEY to enable the map picker.</span>
        @endunless
    </div>
</div>
