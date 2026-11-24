export interface MapLink {
  label: string;
  /** Locale-less app path ("/rooms/3") or an absolute URL. */
  href: string;
  /** "From" price in EUR (converted to the visitor's currency). */
  priceEur?: number | null;
}

export interface MapPlace {
  id: string;
  lat: number;
  lng: number;
  title: string;
  subtitle?: string;
  kind: "building" | "trip" | "office" | "room";
  /** Shown on the marker itself (accommodation map). */
  priceEur?: number | null;
  /** Links listed in the popup and in the text list. */
  links?: MapLink[];
}

export interface MapLabels {
  /** Accessible name of the map region. */
  region: string;
  /** Popup / list link prefix for prices, e.g. "from". */
  from: string;
  /** Text shown while the map script loads. */
  loading: string;
  /** Heading of the text list fallback. */
  listTitle: string;
  /** Short hint for keyboard users. */
  keyboardHint: string;
}

/** Known destination centres (the destination API carries no coordinates). */
export const DESTINATION_CENTERS: Record<string, [number, number]> = {
  "sharm-el-sheikh": [27.9158, 34.33],
  hurghada: [27.2579, 33.8116],
  luxor: [25.6872, 32.6396],
  aswan: [24.0889, 32.8998],
  cairo: [30.0444, 31.2357],
  "marsa-alam": [25.0676, 34.879],
  alexandria: [31.2001, 29.9187],
  dahab: [28.5096, 34.5136],
};

export const hasCoords = (lat: unknown, lng: unknown): boolean =>
  typeof lat === "number" && typeof lng === "number" && Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0);
