"use client";

import { buttonClasses } from "@/components/ui/Button";
import { useGoogleMapsConsent } from "@/lib/consent";
import { directionsUrl, embedUrl, placeUrl } from "@/lib/maps/urls";
import { cn } from "@/lib/utils";
import { ExternalLink, Navigation } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import type { ReactNode } from "react";
import { MapConsentPlaceholder } from "./MapConsent";

interface Props {
  lat: number;
  lng: number;
  /** Place name, used in the iframe title ("Google Maps: …"). */
  name: string;
  /** Accessible name of the map region (default "Map of {name}"). */
  label?: string;
  zoom?: number;
  /** Height of the map area. */
  mapClassName?: string;
  className?: string;
  /** Footer content beside the links (title, address…). */
  children?: ReactNode;
}

/**
 * One location on a Google Maps iframe (Maps Embed API with NEXT_PUBLIC_GOOGLE_MAPS_API_KEY,
 * the keyless maps.google.com embed otherwise), lazy-loaded, behind the cookie consent,
 * with "Open in Google Maps" and "Get directions" links that always work.
 */
export function MapEmbed({ lat, lng, name, label, zoom = 15, mapClassName, className, children }: Props) {
  const t = useTranslations("map");
  const locale = useLocale();
  const consent = useGoogleMapsConsent();
  const point = { lat, lng };
  const title = t("embedTitle", { name });
  return (
    <div className={cn("overflow-hidden rounded-card border border-ink-100 bg-white shadow-card", className)}>
      <div className={cn("relative h-64 bg-ink-100 md:h-80", mapClassName)} role="region" aria-label={label ?? t("mapOf", { name })}>
        {consent === "allowed" ? (
          <iframe
            src={embedUrl(point, { zoom, locale })}
            title={title}
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
            allowFullScreen
            className="absolute inset-0 size-full border-0"
          />
        ) : (
          <MapConsentPlaceholder pending={consent === "pending"} />
        )}
      </div>
      <div className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between md:p-6">
        <div className="min-w-0">{children}</div>
        <div className="flex shrink-0 flex-wrap gap-3">
          <a href={directionsUrl(point)} target="_blank" rel="noopener noreferrer" className={buttonClasses("cta", "sm", "h-11")}>
            <Navigation className="size-4" aria-hidden /> {t("directions")}
          </a>
          <a href={placeUrl(point)} target="_blank" rel="noopener noreferrer" className={buttonClasses("outline", "sm", "h-11")}>
            {t("openInGoogleMaps")} <ExternalLink className="size-4" aria-hidden />
          </a>
        </div>
      </div>
    </div>
  );
}
