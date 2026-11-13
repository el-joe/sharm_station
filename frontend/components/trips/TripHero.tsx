import { AppImage } from "@/components/ui/AppImage";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import type { TripDetail } from "@/lib/api/types";
import { Star } from "lucide-react";
import { useTranslations } from "next-intl";

export function TripHero({ trip, crumbs, duration }: { trip: TripDetail; crumbs: { label: string; href?: string }[]; duration: string }) {
  const t = useTranslations("trip");
  const tc = useTranslations("common");
  return (
    <section className="relative isolate flex min-h-[360px] items-end overflow-hidden text-white md:min-h-[520px]">
      <AppImage src={trip.image.url} alt={trip.image.alt} fill preload sizes="100vw" className="-z-20 object-cover" />
      <div className="absolute inset-0 -z-10 bg-gradient-to-b from-brand-navy-900/5 via-brand-navy-900/30 to-brand-navy-900/80" aria-hidden />
      <div className="container-page pb-8 pt-24 md:pb-12">
        <div className="flex flex-wrap items-center gap-3">
          <Breadcrumbs label={tc("breadcrumbs")} items={crumbs} />
          {trip.badges.includes("best_seller") && <span className="text-eyebrow rounded-pill bg-gold-500 px-2.5 py-1 text-brand-navy-900">{t("bestSeller")}</span>}
          {trip.transfers_included && <span className="text-eyebrow rounded-pill bg-white px-2.5 py-1 text-primary-700">{t("transfersIncluded")}</span>}
        </div>
        <h1 className="text-h1 mt-3 max-w-4xl lg:text-[56px] lg:leading-[64px]">{trip.title}</h1>
        <ul className="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm md:text-base">
          <li>{trip.route?.length ? trip.route.map((r) => r.name).join(" · ") : trip.destination.name}</li>
          <li>{duration}</li>
          {trip.rating != null && (
            <li className="inline-flex items-center gap-1">
              <Star className="size-4 fill-white" aria-hidden />
              <span>{t("rating", { rating: trip.rating, count: trip.reviews_count ?? 0 })}</span>
            </li>
          )}
          <li>{t("reserveWith", { percent: trip.deposit_percent })}</li>
        </ul>
      </div>
    </section>
  );
}
