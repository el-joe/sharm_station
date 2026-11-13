import type { VesselInfo } from "@/lib/api/types";
import { Anchor, BedDouble, Ship, Star, Utensils } from "lucide-react";
import { useTranslations } from "next-intl";

/** Nile trip extras (SRS 4.1): vessel, itinerary, facilities, meal plan, cabins by type only (CR-8: no deck). */
export function NileFacts({ vessel }: { vessel: VesselInfo }) {
  const t = useTranslations("nile");
  return (
    <div className="flex flex-col gap-6">
      <dl className="grid gap-3 sm:grid-cols-3">
        <div className="rounded-image border border-ink-100 bg-white p-4">
          <dt className="text-eyebrow flex items-center gap-2 text-ink-500"><Ship className="size-4" aria-hidden />{t("vessel")}</dt>
          <dd className="mt-1 font-semibold text-ink-900">{vessel.name}</dd>
        </div>
        <div className="rounded-image border border-ink-100 bg-white p-4">
          <dt className="text-eyebrow flex items-center gap-2 text-ink-500"><Anchor className="size-4" aria-hidden />{t("itinerary")}</dt>
          <dd className="mt-1 font-semibold text-ink-900">{t("fromTo", { from: vessel.itinerary_from, to: vessel.itinerary_to })}</dd>
        </div>
        <div className="rounded-image border border-ink-100 bg-white p-4">
          <dt className="text-eyebrow flex items-center gap-2 text-ink-500"><Utensils className="size-4" aria-hidden />{t("meals")}</dt>
          <dd className="mt-1 font-semibold text-ink-900">{vessel.meal_type}</dd>
        </div>
      </dl>
      <div>
        <h3 className="text-lg font-semibold text-ink-900">{t("facilities")}</h3>
        <ul className="mt-3 flex flex-wrap gap-2">
          {vessel.facilities.map((f) => (
            <li key={f} className="rounded-pill bg-primary-100 px-3 py-1.5 text-sm text-primary-700">{f}</li>
          ))}
        </ul>
      </div>
      {vessel.cabins.length > 0 && (
        <div>
          <h3 className="text-lg font-semibold text-ink-900">{t("cabins")}</h3>
          <p className="text-sm text-ink-700">{t("cabinsNote")}</p>
          <ul className="mt-3 grid gap-3 md:grid-cols-3">
            {vessel.cabins.map((c) => (
              <li key={c.type} className="rounded-image border border-ink-100 bg-white p-4">
                <p className="flex items-center gap-2 font-semibold text-ink-900"><BedDouble className="size-4" aria-hidden />{c.type}</p>
                <p className="mt-1 inline-flex" role="img" aria-label={t("stars", { count: c.stars })}>
                  {Array.from({ length: c.stars }, (_, i) => <Star key={i} className="size-3.5 fill-gold-500 text-gold-500" aria-hidden />)}
                </p>
                <p className="mt-1 text-sm text-ink-700">{c.description}</p>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
