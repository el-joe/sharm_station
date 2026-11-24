"use client";

import { useRouter } from "@/i18n/navigation";
import { useCurrency } from "@/lib/currency";
import { loadGoogleMaps } from "@/lib/maps/google";
import { GOOGLE_MAPS_MAP_ID } from "@/lib/maps/urls";
import { useLocale } from "next-intl";
import { useEffect, useRef, useState } from "react";
import type { MapLabels, MapPlace } from "./types";

export interface GoogleMapProps {
  places: MapPlace[];
  /** Used when there is no place. */
  center?: [number, number] | null;
  zoom?: number;
  labels: MapLabels & { error: string };
  /** Highlighted place (e.g. the room card under the pointer). */
  activeId?: string | null;
}

const KIND_COLOURS: Record<MapPlace["kind"], string> = { building: "#e4572e", room: "#e4572e", trip: "#3b5bdb", office: "#070a39" };

const svgUrl = (svg: string) => `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;

function pinSvg(colour: string, active: boolean) {
  const stroke = active ? "#3b5bdb" : "#ffffff";
  return svgUrl(
    `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="40" viewBox="0 0 30 40"><path d="M15 1C7.3 1 1 7.2 1 14.9 1 25.3 15 39 15 39s14-13.7 14-24.1C29 7.2 22.7 1 15 1z" fill="${colour}" stroke="${stroke}" stroke-width="${active ? 3 : 2}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
  );
}

function pillSvg(width: number, active: boolean) {
  const bg = active ? "#1f2f8f" : "#ffffff";
  const border = active ? "#1f2f8f" : "#9aa0b4";
  return svgUrl(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="30" viewBox="0 0 ${width} 30"><rect x="1" y="1" width="${width - 2}" height="26" rx="13" fill="${bg}" stroke="${border}"/></svg>`,
  );
}

/**
 * Google Maps JavaScript API map with one marker per place. AdvancedMarkerElement when
 * NEXT_PUBLIC_GOOGLE_MAPS_MAP_ID is set, classic Marker otherwise; both are keyboard
 * focusable (Tab, Enter opens the info window, Escape closes it). Info windows are built
 * with DOM APIs so API texts are never parsed as HTML. Loaded only in the browser through
 * ./MapView (next/dynamic, ssr: false) once the consent allows it.
 */
export default function GoogleMap({ places, center, zoom = 12, labels, activeId }: GoogleMapProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [failed, setFailed] = useState(false);
  const setActiveRef = useRef<(id: string | null | undefined) => void>(() => undefined);
  const activeRef = useRef(activeId);
  const router = useRouter();
  const routerRef = useRef(router);
  useEffect(() => {
    routerRef.current = router;
  }, [router]);
  const locale = useLocale();
  const { format } = useCurrency();

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let cancelled = false;
    const cleanups: (() => void)[] = [];

    (async () => {
      const [{ Map: GMap, InfoWindow }, markerLib, core] = await Promise.all([loadGoogleMaps("maps", locale), loadGoogleMaps("marker", locale), loadGoogleMaps("core", locale)]);
      if (cancelled) return;
      const fallback = center ? { lat: center[0], lng: center[1] } : { lat: 26.8206, lng: 30.8025 };
      const map = new GMap(el, {
        center: places[0] ? { lat: places[0].lat, lng: places[0].lng } : fallback,
        zoom: places.length ? zoom : center ? zoom : 5,
        gestureHandling: "cooperative",
        clickableIcons: false,
        streetViewControl: false,
        mapTypeControl: false,
        keyboardShortcuts: true,
        ...(GOOGLE_MAPS_MAP_ID ? { mapId: GOOGLE_MAPS_MAP_ID } : {}),
      });
      const info = new InfoWindow({ maxWidth: 280 });
      const bounds = new core.LatLngBounds();
      const updaters: ((active: string | null | undefined) => void)[] = [];

      for (const place of places) {
        const priced = place.priceEur != null && place.priceEur > 0;
        const priceText = priced ? format(place.priceEur!) : "";
        const accessibleName = [place.title, priced ? `${labels.from} ${priceText}` : null].filter(Boolean).join(", ");
        const position = { lat: place.lat, lng: place.lng };
        const content = buildPopup(place, labels.from, format, locale, (href) => routerRef.current.push(href));
        const open = (anchor: google.maps.MVCObject | google.maps.marker.AdvancedMarkerElement) => {
          info.setContent(content);
          info.open({ map, anchor, shouldFocus: true });
        };

        if (GOOGLE_MAPS_MAP_ID) {
          const node = document.createElement("span");
          node.className = priced ? "ssv-gmarker-price" : `ssv-gmarker-pin ssv-gmarker-${place.kind}`;
          node.textContent = priceText;
          const marker = new markerLib.AdvancedMarkerElement({ map, position, title: accessibleName, content: node, gmpClickable: true });
          const onClick = () => open(marker);
          marker.addEventListener("gmp-click", onClick);
          updaters.push((active) => {
            node.classList.toggle("is-active", active === place.id);
            marker.zIndex = active === place.id ? 1000 : null;
          });
          cleanups.push(() => {
            marker.removeEventListener("gmp-click", onClick);
            marker.map = null;
          });
        } else {
          const width = Math.max(44, Math.round(priceText.length * 7.6 + 22));
          const icon = (active: boolean): google.maps.Icon =>
            priced
              ? { url: pillSvg(width, active), scaledSize: new core.Size(width, 30), anchor: new core.Point(width / 2, 15), labelOrigin: new core.Point(width / 2, 14) }
              : { url: pinSvg(KIND_COLOURS[place.kind], active), scaledSize: new core.Size(30, 40), anchor: new core.Point(15, 39) };
          const label = (active: boolean): google.maps.MarkerLabel | null =>
            priced ? { text: priceText, color: active ? "#ffffff" : "#070a39", fontWeight: "600", fontSize: "13px" } : null;
          const marker = new markerLib.Marker({ map, position, title: accessibleName, icon: icon(false), label: label(false), optimized: false });
          const listener = marker.addListener("click", () => open(marker));
          updaters.push((active) => {
            const on = active === place.id;
            marker.setIcon(icon(on));
            marker.setLabel(label(on));
            marker.setZIndex(on ? 1000 : undefined);
          });
          cleanups.push(() => {
            listener.remove();
            marker.setMap(null);
          });
        }
        bounds.extend(position);
      }

      setActiveRef.current = (active) => updaters.forEach((u) => u(active));
      setActiveRef.current(activeRef.current);
      if (places.length > 1) {
        map.fitBounds(bounds, 48);
        // Do not zoom in further than a neighbourhood when the places are close together.
        const once = core.event.addListenerOnce(map, "idle", () => {
          if ((map.getZoom() ?? 0) > 15) map.setZoom(15);
        });
        cleanups.push(() => once.remove());
      }
      cleanups.push(() => info.close());
    })().catch(() => {
      if (!cancelled) setFailed(true);
    });

    return () => {
      cancelled = true;
      setActiveRef.current = () => undefined;
      cleanups.forEach((fn) => fn());
    };
  }, [places, center, zoom, labels, locale, format]);

  useEffect(() => {
    activeRef.current = activeId;
    setActiveRef.current(activeId);
  }, [activeId]);

  if (failed) return <p className="grid size-full place-items-center p-6 text-center text-sm text-ink-700">{labels.error}</p>;
  return <div ref={ref} className="size-full" />;
}

function buildPopup(place: MapPlace, from: string, format: (eur: number) => string, locale: string, navigate: (href: string) => void): HTMLElement {
  const popup = document.createElement("div");
  popup.className = "ssv-popup";
  const title = document.createElement("p");
  title.className = "ssv-popup-title";
  title.textContent = place.title;
  popup.append(title);
  if (place.subtitle) {
    const sub = document.createElement("p");
    sub.className = "ssv-popup-sub";
    sub.textContent = place.subtitle;
    popup.append(sub);
  }
  if (place.links?.length) {
    const list = document.createElement("ul");
    list.className = "ssv-popup-links";
    for (const link of place.links) {
      const li = document.createElement("li");
      const a = document.createElement("a");
      const external = /^https?:\/\//.test(link.href);
      a.href = external ? link.href : `/${locale}${link.href}`;
      if (external) {
        a.target = "_blank";
        a.rel = "noopener noreferrer";
      } else {
        a.addEventListener("click", (e) => {
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
          e.preventDefault();
          navigate(link.href);
        });
      }
      const label = document.createElement("span");
      label.textContent = link.label;
      a.append(label);
      if (link.priceEur != null && link.priceEur > 0) {
        const price = document.createElement("span");
        price.className = "ssv-popup-price";
        price.textContent = `${from} ${format(link.priceEur)}`;
        a.append(price);
      }
      li.append(a);
      list.append(li);
    }
    popup.append(list);
  }
  return popup;
}
