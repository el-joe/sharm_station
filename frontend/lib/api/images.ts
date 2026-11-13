/**
 * The seeded API returns placeholder paths such as "/images/placeholders/trip-1.jpg"
 * that do not exist in this app. Until real media is uploaded, every API image
 * path is resolved here to one of the photos in /public/images, chosen by the
 * product slug/type, so pages never show a broken image. Absolute URLs (real
 * uploads served by the API) are passed through untouched.
 */
import { PUBLIC_API_URL } from "../site";

const IMG = {
  cairo: "/images/cairo.webp",
  luxor: "/images/luxor.webp",
  aswan: "/images/aswan.webp",
  sharm: "/images/sharm.webp",
  hurghada: "/images/hurghada.webp",
  alexandria: "/images/alexandria.webp",
  nile: "/images/nile-cruise.webp",
  balloons: "/images/balloons.webp",
  roomDeluxe: "/images/room-deluxe.webp",
  roomFamily: "/images/room-family.webp",
  airport: "/images/airport.webp",
} as const;

const API_ORIGIN = (() => {
  try {
    return new URL(PUBLIC_API_URL).origin;
  } catch {
    return "";
  }
})();

const isPlaceholder = (path: string) => path.includes("/placeholders/");

/** Real uploaded media: absolute URLs or API-relative storage paths. */
function passthrough(path: string | null | undefined): string | null {
  if (!path || isPlaceholder(path)) return null;
  if (/^https?:\/\//.test(path)) return path;
  if (path.startsWith("/storage/")) return `${API_ORIGIN}${path}`;
  return null;
}

/** Keyword -> photo pool, first match wins. */
const RULES: [RegExp, string[]][] = [
  [/nile-sun|cruise/, [IMG.nile, IMG.aswan, IMG.luxor, IMG.balloons]],
  [/felucca|aswan|philae|abu-simbel/, [IMG.aswan, IMG.nile, IMG.luxor]],
  [/balloon/, [IMG.balloons, IMG.luxor]],
  [/luxor|valley|karnak|east-west/, [IMG.luxor, IMG.balloons, IMG.nile]],
  [/pyramid|giza|cairo|museum/, [IMG.cairo, IMG.luxor, IMG.alexandria]],
  [/snorkel|ras-mohammed|reef|diving/, [IMG.sharm, IMG.hurghada]],
  [/giftun|island|hurghada|boat/, [IMG.hurghada, IMG.sharm]],
  [/buggy|quad|safari|wadi|desert|marsa/, [IMG.hurghada, IMG.sharm, IMG.cairo]],
  [/alexandria/, [IMG.alexandria]],
  [/sharm/, [IMG.sharm, IMG.hurghada]],
  [/classic-egypt|package/, [IMG.cairo, IMG.luxor, IMG.aswan, IMG.nile]],
];

function pool(key: string): string[] {
  const k = key.toLowerCase();
  return RULES.find(([re]) => re.test(k))?.[1] ?? [IMG.cairo, IMG.luxor, IMG.sharm];
}

/** Trip / Nile / package images; `index` rotates through the pool for galleries. */
export function tripImage(path: string | null | undefined, slug: string, index = 0): string {
  return passthrough(path) ?? pool(slug)[index % pool(slug).length];
}

export function destinationImage(path: string | null | undefined, slug: string): string {
  const direct = passthrough(path);
  if (direct) return direct;
  const map: Record<string, string> = {
    cairo: IMG.cairo,
    luxor: IMG.luxor,
    aswan: IMG.aswan,
    "sharm-el-sheikh": IMG.sharm,
    hurghada: IMG.hurghada,
    "marsa-alam": IMG.hurghada,
    alexandria: IMG.alexandria,
  };
  return map[slug] ?? pool(slug)[0];
}

export function roomImage(path: string | null | undefined, roomType: string, index = 0): string {
  const direct = passthrough(path);
  if (direct) return direct;
  const family = /family|triple|suite/.test(roomType);
  const list = family ? [IMG.roomFamily, IMG.roomDeluxe] : [IMG.roomDeluxe, IMG.roomFamily];
  return list[index % list.length];
}

export function buildingImage(path: string | null | undefined, slug: string, destinationSlug?: string): string {
  const direct = passthrough(path);
  if (direct) return direct;
  if (/nile/.test(slug)) return IMG.roomDeluxe;
  return destinationImage(null, destinationSlug ?? slug);
}

export function addonImage(path: string | null | undefined, name: string, fallbackSlug: string): string {
  const direct = passthrough(path);
  if (direct) return direct;
  const n = name.toLowerCase();
  if (/balloon/.test(n)) return IMG.balloons;
  if (/lunch|dinner|meal/.test(n)) return IMG.aswan;
  return tripImage(null, fallbackSlug);
}
