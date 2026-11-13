"use client";

import { RoomCard } from "@/components/accommodation/RoomCard";
import { TripCard } from "@/components/trips/TripCard";
import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Link } from "@/i18n/navigation";
import { useFavourites } from "@/lib/favourites";
import { Heart, HeartOff } from "lucide-react";
import { useTranslations } from "next-intl";

/** /account/favourites: cards saved with the heart toggle (localStorage snapshot). */
export function FavouritesList() {
  const t = useTranslations("account.favourites");
  const { list, remove } = useFavourites();
  const trips = list.filter((f) => f.kind === "trip");
  const rooms = list.filter((f) => f.kind === "room");

  if (list.length === 0) {
    return (
      <EmptyState
        className="mt-8"
        icon={<Heart aria-hidden />}
        title={t("emptyTitle")}
        text={t("emptyText")}
        action={<Link href="/destinations" className={buttonClasses("cta", "md")}>{t("explore")}</Link>}
      />
    );
  }

  const removeBtn = (key: string) => (
    <button type="button" onClick={() => remove(key)} className="mt-2 inline-flex min-h-11 items-center gap-2 px-2 text-sm font-semibold text-ink-700 hover:text-error">
      <HeartOff className="size-4" aria-hidden /> {t("remove")}
    </button>
  );

  return (
    <div className="mt-6 flex flex-col gap-10">
      <p className="inline-flex self-start rounded-pill bg-ink-100 px-3 py-1 text-sm font-semibold text-ink-900">
        {t("count", { count: list.length })}
        <span className="sr-only">. {t("deviceNote")}</span>
      </p>
      {trips.length > 0 && (
        <section aria-labelledby="fav-trips">
          <h2 id="fav-trips" className="text-h4 mb-4 text-ink-900">{t("trips")}</h2>
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {trips.map((f) => (
              <li key={f.key} className="flex flex-col">
                {f.kind === "trip" && <TripCard trip={f.item} className="flex-1" />}
                {removeBtn(f.key)}
              </li>
            ))}
          </ul>
        </section>
      )}
      {rooms.length > 0 && (
        <section aria-labelledby="fav-rooms">
          <h2 id="fav-rooms" className="text-h4 mb-4 text-ink-900">{t("rooms")}</h2>
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {rooms.map((f) => (
              <li key={f.key} className="flex flex-col">
                {f.kind === "room" && <RoomCard room={f.item} className="flex-1" />}
                {removeBtn(f.key)}
              </li>
            ))}
          </ul>
        </section>
      )}
      <section className="rounded-card border border-ink-100 bg-white p-6 shadow-card" aria-labelledby="fav-cta">
        <h2 id="fav-cta" className="font-display text-xl font-semibold text-ink-900">{t("ctaTitle")}</h2>
        <p className="mt-1 text-ink-700">{t("ctaText")}</p>
        <Link href="/destinations" className={buttonClasses("outline", "md", "mt-4")}>{t("explore")}</Link>
        <p className="mt-3 text-xs text-ink-500">{t("deviceNote")}</p>
      </section>
    </div>
  );
}
