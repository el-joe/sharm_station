/**
 * <div data-location-picker data-lat-input="#f-lat" data-lng-input="#f-lng" [data-address-input="#f-address"] [data-maps-key="…"]>
 *
 * Always keeps the "Preview on Google Maps" link in sync with the lat/lng inputs. With a browser key it loads the
 * Google Maps JavaScript API once (Places library), shows a map with a draggable marker, click-to-place and a
 * Places Autocomplete search box, and writes the coordinates (and the address, where the form has one) into the inputs.
 */
const CALLBACK = '__sharmStationMapsReady';
let mapsPromise = null;

export function initLocationPickers(root = document) {
    root.querySelectorAll('[data-location-picker]').forEach((picker) => {
        if (picker.dataset.locationReady) {
            return;
        }

        picker.dataset.locationReady = '1';
        const fields = {
            lat: document.querySelector(picker.dataset.latInput),
            lng: document.querySelector(picker.dataset.lngInput),
            address: picker.dataset.addressInput ? document.querySelector(picker.dataset.addressInput) : null,
        };

        if (!fields.lat || !fields.lng) {
            return;
        }

        const preview = picker.querySelector('[data-location-preview]');
        const syncPreview = () => {
            const point = readPoint(fields);

            if (preview) {
                preview.hidden = point === null;
                preview.href = point ? `https://www.google.com/maps?q=${point.lat},${point.lng}` : 'https://www.google.com/maps';
            }
        };

        fields.lat.addEventListener('input', syncPreview);
        fields.lng.addEventListener('input', syncPreview);
        syncPreview();

        if (picker.dataset.mapsKey) {
            loadGoogleMaps(picker.dataset.mapsKey)
                .then(() => setUpMap(picker, fields, syncPreview))
                .catch(() => showMapError(picker));
        }
    });
}

function readPoint(fields) {
    const lat = parseFloat(String(fields.lat.value).replace(',', '.'));
    const lng = parseFloat(String(fields.lng.value).replace(',', '.'));

    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
        return null;
    }

    return { lat, lng };
}

function loadGoogleMaps(key) {
    if (window.google?.maps?.importLibrary) {
        return Promise.resolve(window.google.maps);
    }

    if (mapsPromise) {
        return mapsPromise;
    }

    mapsPromise = new Promise((resolve, reject) => {
        window[CALLBACK] = () => resolve(window.google.maps);
        window.gm_authFailure = () => document.querySelectorAll('[data-location-picker]').forEach(showMapError);

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&libraries=places&loading=async&callback=${CALLBACK}`;
        script.async = true;
        script.onerror = () => {
            mapsPromise = null;
            reject(new Error('Google Maps could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return mapsPromise;
}

async function setUpMap(picker, fields, syncPreview) {
    const mapElement = picker.querySelector('[data-location-map]');

    if (!mapElement) {
        return;
    }

    const { Map } = await window.google.maps.importLibrary('maps');
    const { Marker } = await window.google.maps.importLibrary('marker');
    const disabled = fields.lat.matches(':disabled') || fields.lat.readOnly;
    const current = readPoint(fields);
    const fallbackCenter = { lat: parseFloat(picker.dataset.defaultLat) || 27.9158, lng: parseFloat(picker.dataset.defaultLng) || 34.33 };

    const map = new Map(mapElement, {
        center: current ?? fallbackCenter,
        zoom: current ? 15 : 11,
        mapTypeControl: false,
        streetViewControl: false,
        clickableIcons: false,
        gestureHandling: 'cooperative',
    });
    const marker = new Marker({ map, position: current, draggable: !disabled });

    if (disabled) {
        return;
    }

    const write = (input, value) => {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const placeAt = (latLng, pan = false) => {
        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
        marker.setPosition({ lat, lng });
        write(fields.lat, lat.toFixed(7));
        write(fields.lng, lng.toFixed(7));
        syncPreview();

        if (pan) {
            map.panTo({ lat, lng });
        }
    };

    map.addListener('click', (event) => event.latLng && placeAt(event.latLng));
    marker.addListener('dragend', (event) => event.latLng && placeAt(event.latLng));

    // Coordinates typed by hand move the marker.
    [fields.lat, fields.lng].forEach((input) => input.addEventListener('change', () => {
        const point = readPoint(fields);

        if (point) {
            marker.setPosition(point);
            map.panTo(point);
        }
    }));

    await setUpSearch(picker, fields, map, placeAt, write);
}

async function setUpSearch(picker, fields, map, placeAt, write) {
    const container = picker.querySelector('[data-location-search]');

    if (!container) {
        return;
    }

    try {
        const { PlaceAutocompleteElement } = await window.google.maps.importLibrary('places');

        if (!PlaceAutocompleteElement) {
            container.closest('[data-location-map-wrap]')?.querySelector('.form-label')?.setAttribute('hidden', '');

            return;
        }

        const autocomplete = new PlaceAutocompleteElement({});
        autocomplete.setAttribute('aria-label', container.dataset.label ?? 'Search a place');
        autocomplete.classList.add('block', 'w-full');
        container.appendChild(autocomplete);

        const usePlace = async (place) => {
            if (!place) {
                return;
            }

            await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });

            if (!place.location) {
                return;
            }

            placeAt(place.location, true);
            map.setZoom(16);

            if (fields.address && place.formattedAddress) {
                write(fields.address, place.formattedAddress);
            }
        };

        // Current event (gmp-select) and the earlier beta event (gmp-placeselect).
        autocomplete.addEventListener('gmp-select', (event) => usePlace(event.placePrediction?.toPlace()));
        autocomplete.addEventListener('gmp-placeselect', (event) => usePlace(event.place));
    } catch {
        container.hidden = true;
    }
}

function showMapError(picker) {
    const status = picker.querySelector('[data-location-status]');

    if (status) {
        status.textContent = 'Google Maps could not be loaded (check GOOGLE_MAPS_BROWSER_KEY and its allowed APIs/referrers). Enter the coordinates by hand.';
        status.classList.add('text-error');
    }
}
