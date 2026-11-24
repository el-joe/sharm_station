"use client";

import { buttonClasses } from "@/components/ui/Button";
import { useGoogleMapsConsent } from "@/lib/consent";
import { loadGoogleMaps } from "@/lib/maps/google";
import { directionsUrl, GOOGLE_MAPS_MAP_ID, HAS_GOOGLE_MAPS_KEY, type LatLng } from "@/lib/maps/urls";
import { cn } from "@/lib/utils";
import { ExternalLink, Route } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";
import { MapConsentPlaceholder } from "./MapConsent";

interface Props {
  origin: LatLng | null;
  originName: string;
  /** Coordinates of the drop-off, when known (picked suggestion); otherwise the typed text is used. */
  destination: LatLng | null;
  destinationName: string;
  className?: string;
}

type RouteState = { status: "loading" } | { status: "ok"; distance: string; duration: string } | { status: "error" };

/**
 * Transfer route preview. With NEXT_PUBLIC_GOOGLE_MAPS_API_KEY (and consent): the driving route on a
 * Google map (DirectionsService + DirectionsRenderer) with Google's distance / time. Always: a
 * "View route on Google Maps" link (the only preview without a key).
 */
export function RoutePreview({ origin, originName, destination, destinationName, className }: Props) {
  const t = useTranslations("map");
  const consent = useGoogleMapsConsent();
  const routeLink = directionsUrl(destination ?? destinationName, origin ?? originName);
  const canDraw = HAS_GOOGLE_MAPS_KEY && origin !== null && destination !== null;
  const label = t("routeLabel", { from: originName, to: destinationName });

  return (
    <section className={cn("overflow-hidden rounded-card border border-ink-100 bg-white", className)} aria-label={t("routeTitle")}>
      {canDraw && (
        <div className="relative h-64 bg-ink-100 md:h-80" role="region" aria-label={label}>
          {consent === "allowed" ? <RouteMap origin={origin} destination={destination} /> : <MapConsentPlaceholder pending={consent === "pending"} />}
        </div>
      )}
      <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
        <p className="flex items-center gap-2 text-sm text-ink-700">
          <Route className="size-5 shrink-0 text-primary-700" aria-hidden />
          <span>{label}</span>
        </p>
        <a href={routeLink} target="_blank" rel="noopener noreferrer" className={buttonClasses("outline", "sm", "h-11 shrink-0")}>
          {t("viewRoute")} <ExternalLink className="size-4" aria-hidden />
        </a>
      </div>
    </section>
  );
}

function RouteMap({ origin, destination }: { origin: LatLng; destination: LatLng }) {
  const t = useTranslations("map");
  const locale = useLocale();
  const ref = useRef<HTMLDivElement>(null);
  const [state, setState] = useState<RouteState>({ status: "loading" });
  const { lat: oLat, lng: oLng } = origin;
  const { lat: dLat, lng: dLng } = destination;

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let cancelled = false;
    let renderer: google.maps.DirectionsRenderer | null = null;
    setState({ status: "loading" });
    (async () => {
      const [{ Map: GMap }, routes] = await Promise.all([loadGoogleMaps("maps", locale), loadGoogleMaps("routes", locale)]);
      if (cancelled) return;
      const map = new GMap(el, {
        center: { lat: oLat, lng: oLng },
        zoom: 10,
        gestureHandling: "cooperative",
        clickableIcons: false,
        streetViewControl: false,
        mapTypeControl: false,
        ...(GOOGLE_MAPS_MAP_ID ? { mapId: GOOGLE_MAPS_MAP_ID } : {}),
      });
      const result = await new routes.DirectionsService().route({ origin: { lat: oLat, lng: oLng }, destination: { lat: dLat, lng: dLng }, travelMode: google.maps.TravelMode.DRIVING });
      if (cancelled) return;
      renderer = new routes.DirectionsRenderer({ map, directions: result, polylineOptions: { strokeColor: "#1f2f8f", strokeWeight: 5 } });
      const leg = result.routes[0]?.legs[0];
      setState(leg?.distance && leg.duration ? { status: "ok", distance: leg.distance.text, duration: leg.duration.text } : { status: "error" });
    })().catch(() => {
      if (!cancelled) setState({ status: "error" });
    });
    return () => {
      cancelled = true;
      renderer?.setMap(null);
    };
  }, [oLat, oLng, dLat, dLng, locale]);

  return (
    <>
      <div ref={ref} className="size-full" />
      <p className="absolute inset-x-3 bottom-3 rounded-control bg-white/95 px-3 py-2 text-sm font-medium text-ink-900 shadow-card" aria-live="polite">
        {state.status === "loading" ? t("routeLoading") : state.status === "ok" ? t("routeSummary", { distance: state.distance, duration: state.duration }) : t("routeError")}
      </p>
    </>
  );
}
