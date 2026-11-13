import type { TripSummary } from "@/lib/api/types";

/** Public URL for a product, by type (docs/ARCHITECTURE.md §7). */
export function productHref(t: Pick<TripSummary, "slug" | "type">) {
  if (t.type === "nile") return `/nile/${t.slug}`;
  if (t.type === "package") return `/packages/${t.slug}`;
  return `/trips/${t.slug}`;
}
