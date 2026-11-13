import { FavouriteButton } from "@/components/account/FavouriteButton";
import { AppImage } from "@/components/ui/AppImage";
import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { buttonClasses } from "@/components/ui/Button";
import { Stars } from "@/components/ui/Stars";
import { Link } from "@/i18n/navigation";
import type { TripSummary } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { Clock, MapPin } from "lucide-react";
import { useTranslations } from "next-intl";
import { productHref } from "./paths";

/** Figma TripCard (20:8338). Also used for packages (route chips + Transfers included). */
export function TripCard({ trip, className, priority = false }: { trip: TripSummary; className?: string; priority?: boolean }) {
  const t = useTranslations("card");
  const href = productHref(trip);
  const duration =
    trip.days && trip.days > 1
      ? trip.nights
        ? t("daysNights", { days: trip.days, nights: trip.nights })
        : t("days", { days: trip.days })
      : t("hours", { hours: trip.duration_hours ?? 0 });
  const location = trip.route?.length ? trip.route.map((r) => r.name).join(", ") : trip.destination.name;
  const badge = trip.badges.includes("transfers_included") ? (
    <Badge tone="info" icon={BadgeIcons.transfers}>{t("transfersIncluded")}</Badge>
  ) : trip.badges.includes("best_seller") ? (
    <Badge tone="brand" className="bg-gold-500" icon={BadgeIcons.bestSeller}>{t("bestSeller")}</Badge>
  ) : trip.free_cancellation ? (
    <Badge tone="success" icon={BadgeIcons.freeCancellation}>{t("freeCancellation")}</Badge>
  ) : trip.badges.includes("deposit_only") ? (
    <Badge tone="info" icon={BadgeIcons.deposit}>{t("depositOnly")}</Badge>
  ) : null;

  return (
    <article className={cn("group relative flex flex-col rounded-card border border-ink-100 bg-white p-2 shadow-card transition-shadow hover:shadow-raised", className)}>
      <div className="relative aspect-[264/215] overflow-hidden rounded-image bg-ink-100">
        <AppImage
          src={trip.image.url}
          alt={trip.image.alt}
          fill
          sizes="(min-width: 1024px) 282px, (min-width: 640px) 50vw, 100vw"
          className="object-cover transition-transform duration-500 group-hover:scale-105"
          preload={priority}
        />
        {badge && <div className="absolute start-3 top-3 rounded-pill bg-white">{badge}</div>}
        <FavouriteButton kind="trip" item={trip} />
      </div>
      <div className="flex flex-1 flex-col gap-2 px-2 pb-2 pt-3">
        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-700">
          <span className="inline-flex items-center gap-1">
            <MapPin className="size-4" aria-hidden />
            {location}
          </span>
          <span className="inline-flex items-center gap-1">
            <Clock className="size-4" aria-hidden />
            {duration}
          </span>
        </p>
        <h3 className="text-base font-semibold leading-snug text-ink-900">
          <Link href={href} className="after:absolute after:inset-0 after:rounded-card focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-primary-500">
            {trip.title}
          </Link>
        </h3>
        {trip.route && trip.route.length > 0 && (
          <ol className="flex flex-wrap items-center gap-1 text-xs font-medium text-primary-700" aria-label={t("route")}>
            {trip.route.map((r, i) => (
              <li key={r.name} className="flex items-center gap-1">
                <span className="rounded-pill bg-primary-100 px-2 py-0.5">{t("stageChip", { name: r.name, days: r.days })}</span>
                {i < trip.route!.length - 1 && <span aria-hidden className="h-0.5 w-3 bg-primary-700" />}
              </li>
            ))}
          </ol>
        )}
        {trip.rating != null && (
          <p className="flex items-center gap-1.5 text-sm text-ink-700">
            <Stars rating={trip.rating} label={t("ratingAria", { rating: trip.rating })} />
            <span className="text-ink-900">{trip.rating.toFixed(1)}</span>
            {trip.reviews_count != null && <span>({t("reviews", { count: trip.reviews_count })})</span>}
          </p>
        )}
        <div className="mt-auto flex items-end justify-between gap-3 pt-2">
          <div>
            <p className="text-xs text-ink-500">{t("from")}</p>
            <Price amount={trip.price_from} className="text-[22px] font-semibold leading-7 text-ink-900" />
            <p className="text-xs text-ink-500">{trip.pricing_mode === "per_unit" ? t("perUnit", { unit: trip.unit_label ?? "unit" }) : t("perPerson")}</p>
          </div>
          <span className={buttonClasses("primary", "sm", "relative z-10 pointer-events-none")} aria-hidden>
            {t("bookNow")}
          </span>
        </div>
      </div>
    </article>
  );
}
