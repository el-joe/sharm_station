"use client";

import { Link } from "@/i18n/navigation";
import { useGoogleMapsConsent } from "@/lib/consent";
import { Price } from "@/lib/currency";
import { areaEmbedUrl, fitView, HAS_GOOGLE_MAPS_KEY, placeUrl } from "@/lib/maps/urls";
import { cn } from "@/lib/utils";
import { ExternalLink, MapPin } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import dynamic from "next/dynamic";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { MapConsentPlaceholder } from "./MapConsent";
import type { MapPlace } from "./types";

function MapSkeleton({ label }: { label: string }) {
  return (
    <div className="grid size-full place-items-center bg-[linear-gradient(90deg,var(--color-ink-100)_1px,transparent_1px),linear-gradient(var(--color-ink-100)_1px,transparent_1px)] bg-[size:48px_48px] bg-white motion-safe:animate-pulse">
      <span className="flex items-center gap-2 text-sm text-ink-500">
        <MapPin className="size-4" aria-hidden /> {label}
      </span>
    </div>
  );
}

// The Maps JavaScript API needs `window`: loaded in the browser only, in its own chunk.
const GoogleMap = dynamic(() => import("./GoogleMap"), { ssr: false, loading: () => <MapSkeleton label="…" /> });

interface Props {
  places: MapPlace[];
  center?: [number, number] | null;
  zoom?: number;
  /** Height / shape of the map box. */
  className?: string;
  /** "visible": text list under the map; "sr-only": the page already lists the places visually (interactive map only). */
  list?: "visible" | "sr-only";
  activeId?: string | null;
  /** Accessible name of the map. */
  label: string;
}

/**
 * Map with several places.
 *
 * - With NEXT_PUBLIC_GOOGLE_MAPS_API_KEY: Google Maps JavaScript API with one marker per place
 *   (./GoogleMap), fetched only when the map scrolls near the viewport.
 * - Without a key: the keyless Google Maps embed centred on the area, and the text list of
 *   places (always visible) with an "Open in Google Maps" link per place.
 *
 * Google loads only with the cookie consent ("Accept all") or after "Show map". A plain list of
 * the same places is always rendered, so the content works without JavaScript, for screen
 * readers and when Google cannot load.
 */
export function MapView({ places, center, zoom, className, list = "visible", activeId, label }: Props) {
  const t = useTranslations("map");
  const locale = useLocale();
  const consent = useGoogleMapsConsent();
  const box = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const el = box.current;
    if (!el || visible || !HAS_GOOGLE_MAPS_KEY) return;
    const io = new IntersectionObserver((entries) => entries.some((e) => e.isIntersecting) && setVisible(true), { rootMargin: "300px" });
    io.observe(el);
    return () => io.disconnect();
  }, [visible]);

  const labels = useMemo(
    () => ({ region: label, from: t("from"), loading: t("loading"), listTitle: t("listTitle"), keyboardHint: t("keyboardHint"), error: t("loadError") }),
    [label, t],
  );

  const view = useMemo(
    () => fitView(places.map((p) => ({ lat: p.lat, lng: p.lng })), center ? { lat: center[0], lng: center[1] } : null, zoom ?? 11),
    [places, center, zoom],
  );

  let map: ReactNode;
  if (consent !== "allowed") map = <MapConsentPlaceholder pending={consent === "pending"} />;
  else if (HAS_GOOGLE_MAPS_KEY) map = visible ? <GoogleMap places={places} center={center} zoom={zoom} labels={labels} activeId={activeId} /> : <MapSkeleton label={t("loading")} />;
  else if (view)
    map = <iframe src={areaEmbedUrl(view.center, { zoom: view.zoom, locale })} title={t("embedTitle", { name: label })} loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen className="absolute inset-0 size-full border-0" />;
  else map = null;

  const listHidden = HAS_GOOGLE_MAPS_KEY && list === "sr-only";

  return (
    <div>
      <div ref={box} role="region" aria-label={label} className={cn("relative isolate z-0 h-[320px] overflow-hidden rounded-card border border-ink-100 bg-ink-100 md:h-[420px]", className)}>
        {map}
      </div>
      {HAS_GOOGLE_MAPS_KEY && places.length > 0 && <p className="sr-only">{t("keyboardHint")}</p>}
      {places.length > 0 && (
        <div className={listHidden ? "sr-only" : "mt-4"}>
          <h3 className="text-sm font-semibold text-ink-900">{t("listTitle")}</h3>
          <ul className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {places.map((p) => (
              <li key={p.id} className={cn("flex items-start gap-2 rounded-control bg-white p-3 text-sm shadow-card", activeId === p.id && "ring-2 ring-primary-500")}>
                <MapPin className="mt-0.5 size-4 shrink-0 text-cta-600" aria-hidden />
                <div className="min-w-0">
                  <p className="font-semibold text-ink-900">{p.title}</p>
                  {p.subtitle && <p className="text-ink-500">{p.subtitle}</p>}
                  {p.links?.map((l) =>
                    /^https?:\/\//.test(l.href) ? (
                      <a key={l.href} href={l.href} target="_blank" rel="noopener noreferrer" className="block font-semibold text-primary-700 underline">
                        {l.label}
                      </a>
                    ) : (
                      <Link key={l.href} href={l.href} className="block text-primary-700 underline">
                        {l.label}
                        {l.priceEur ? (
                          <>
                            {" · "}
                            {t("from")} <Price amount={l.priceEur} />
                          </>
                        ) : null}
                      </Link>
                    ),
                  )}
                  <a
                    href={placeUrl({ lat: p.lat, lng: p.lng })}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`${t("openInGoogleMaps")}: ${p.title}`}
                    className="mt-1 inline-flex items-center gap-1 text-primary-700 underline"
                  >
                    {t("openInGoogleMaps")}
                    <ExternalLink className="size-3.5" aria-hidden />
                  </a>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
