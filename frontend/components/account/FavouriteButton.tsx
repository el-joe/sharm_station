"use client";

import type { RoomSummary, TripSummary } from "@/lib/api/types";
import { favouriteKey, useFavourites } from "@/lib/favourites";
import { cn } from "@/lib/utils";
import { Heart } from "lucide-react";
import { useTranslations } from "next-intl";

type Props = { kind: "trip"; item: TripSummary; className?: string } | { kind: "room"; item: RoomSummary; className?: string };

/** Heart toggle on TripCard / RoomCard (favourites live in localStorage, see lib/favourites.ts). */
export function FavouriteButton(props: Props) {
  const t = useTranslations("favourite");
  const { has, toggle } = useFavourites();
  const key = favouriteKey(props.kind, props.kind === "trip" ? props.item.slug : props.item.id);
  const name = props.kind === "trip" ? props.item.title : props.item.name;
  const active = has(key);
  return (
    <button
      type="button"
      aria-pressed={active}
      aria-label={active ? t("remove", { name }) : t("add", { name })}
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        toggle(props.kind === "trip" ? { kind: "trip", key, item: props.item } : { kind: "room", key, item: props.item });
      }}
      className={cn(
        "absolute end-3 top-3 z-10 grid size-10 place-items-center rounded-full bg-white/95 text-ink-900 shadow-card transition hover:scale-105 focus-visible:outline-2 focus-visible:outline-primary-500",
        props.className,
      )}
    >
      <Heart className={cn("size-5 transition-colors", active && "fill-cta-600 text-cta-600")} aria-hidden />
    </button>
  );
}
