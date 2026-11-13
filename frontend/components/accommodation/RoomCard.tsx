import { FavouriteButton } from "@/components/account/FavouriteButton";
import { AppImage } from "@/components/ui/AppImage";
import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import type { RoomSummary } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { BedDouble, Building2, MapPin, Star, Users, Waves, Wifi, UtensilsCrossed } from "lucide-react";
import { useTranslations } from "next-intl";

const amenityIcon = (name: string) => {
  const n = name.toLowerCase();
  if (n.includes("wi")) return <Wifi className="size-4" aria-hidden />;
  if (n.includes("break") || n.includes("colaz")) return <UtensilsCrossed className="size-4" aria-hidden />;
  return <Waves className="size-4" aria-hidden />;
};

/** Figma RoomCard (22:8729): building strip + "Tax included" badge (CR-2). */
export function RoomCard({ room, nights, className, query }: { room: RoomSummary; nights?: number; className?: string; query?: Record<string, string> }) {
  const t = useTranslations("room");
  const guests = room.max_children > 0 ? t("adultsChildren", { adults: room.max_adults, children: room.max_children }) : t("adults", { count: room.max_adults });
  return (
    <article className={cn("group relative flex flex-col gap-3 rounded-card border border-ink-100 bg-white p-2 shadow-card transition-shadow hover:shadow-raised", className)}>
      <div className="relative aspect-[264/240] overflow-hidden rounded-image bg-ink-100">
        <AppImage src={room.image.url} alt={room.image.alt} fill sizes="(min-width: 1024px) 282px, (min-width: 640px) 50vw, 100vw" className="object-cover transition-transform duration-500 group-hover:scale-105" />
        <div className="absolute start-3 top-3 flex flex-col items-start gap-1">
          {room.free_cancellation && (
            <span className="rounded-pill bg-white"><Badge tone="success" icon={BadgeIcons.freeCancellation}>{t("freeCancellation")}</Badge></span>
          )}
          {room.breakfast_included && (
            <span className="rounded-pill bg-white"><Badge tone="brand" icon={BadgeIcons.breakfast}>{t("breakfastIncluded")}</Badge></span>
          )}
          {room.few_left && (
            <span className="rounded-pill bg-white"><Badge tone="warning" icon={BadgeIcons.fewLeft}>{t("fewLeft")}</Badge></span>
          )}
        </div>
        <FavouriteButton kind="room" item={room} />
      </div>
      <div className="flex flex-1 flex-col gap-2 px-2 pb-2">
        <div>
          <h3 className="text-base font-semibold text-ink-900">
            <Link href={{ pathname: `/rooms/${room.id}`, query }} className="after:absolute after:inset-0 after:rounded-card">
              {room.name}
            </Link>
          </h3>
          <p className="text-sm text-ink-700">{room.type_label}</p>
        </div>
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-sm text-ink-700">
          <li className="inline-flex items-center gap-1"><Users className="size-4" aria-hidden />{guests}</li>
          <li className="inline-flex items-center gap-1"><BedDouble className="size-4" aria-hidden />{room.bed_label} · {room.size_m2} m²</li>
        </ul>
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-ink-700" aria-label={t("amenities")}>
          {room.amenities.slice(0, 3).map((a) => (
            <li key={a} className="inline-flex items-center gap-1">{amenityIcon(a)}{a}</li>
          ))}
        </ul>
        <div className="rounded-control bg-sand-50 px-3 py-2">
          <p className="flex items-center gap-2 text-sm text-ink-900">
            <Building2 className="size-4 shrink-0" aria-hidden />
            <span className="truncate">{room.building.name}</span>
            <span className="inline-flex shrink-0" role="img" aria-label={t("stars", { count: room.building.stars })}>
              {Array.from({ length: room.building.stars }, (_, i) => (
                <Star key={i} className="size-3.5 fill-gold-500 text-gold-500" aria-hidden />
              ))}
            </span>
          </p>
          <p className="mt-0.5 flex items-center gap-1 text-sm text-ink-700">
            <MapPin className="size-4" aria-hidden />
            {room.building.location}
          </p>
        </div>
        <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-1">
          <div className="flex flex-col gap-1">
            <p className="flex items-end gap-1">
              <Price amount={room.price_per_night} className="text-[22px] font-semibold leading-7 text-ink-900" />
              <span className="pb-0.5 text-xs font-medium text-ink-500">{t("perNight")}</span>
            </p>
            {nights && nights > 0 ? (
              <p className="text-xs font-medium text-ink-700">
                {t("totalFor", { nights })} <Price amount={room.price_per_night * nights} />
              </p>
            ) : null}
            {room.tax_included ? (
              <Badge tone="success" icon={BadgeIcons.taxIncluded} className="self-start">{t("taxIncluded")}</Badge>
            ) : room.tax_per_night ? (
              <p className="text-xs font-medium text-ink-700">
                {t("taxExtraPrefix")} <Price amount={room.tax_per_night} decimals /> {t("taxExtraSuffix")}
              </p>
            ) : (
              <p className="text-xs font-medium text-ink-700">{t("taxNotIncluded")}</p>
            )}
          </div>
          <span className={buttonClasses("primary", "sm", "pointer-events-none")} aria-hidden>
            {t("bookNow")}
          </span>
        </div>
      </div>
    </article>
  );
}
