"use client";

import { Link, useRouter } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth";
import { useFavourites } from "@/lib/favourites";
import { cn } from "@/lib/utils";
import { Briefcase, CalendarCheck, ChevronDown, Heart, LogOut, Settings, UserRound } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState } from "react";

const triggerClass = "inline-flex h-11 items-center gap-2 rounded-control border border-primary-700 px-3 font-semibold text-primary-700 hover:bg-primary-100 lg:px-4";

/** Header account control: "Sign in" when anonymous, an account disclosure menu when signed in. */
export function AccountMenu() {
  const t = useTranslations("header");
  const { status, user, isPartner, signOut } = useAuth();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => !rootRef.current?.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  if (status === "loading") {
    return <span className={cn(triggerClass, "pointer-events-none w-11 border-ink-100 lg:w-28")} aria-hidden />;
  }

  if (status === "anonymous") {
    return (
      <Link href="/sign-in" className={triggerClass}>
        <UserRound className="size-5" aria-hidden />
        <span className="max-sm:sr-only">{t("signIn")}</span>
      </Link>
    );
  }

  const name = user?.name ?? t("account");
  const first = name.split(" ")[0];
  const items = [
    { href: "/account/bookings", label: t("myBookings"), Icon: CalendarCheck },
    { href: "/account/favourites", label: t("favourites"), Icon: Heart },
    { href: "/account/profile", label: t("profile"), Icon: Settings },
    ...(isPartner ? [{ href: "/partner", label: t("partnerPanel"), Icon: Briefcase }] : []),
  ];

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        className={triggerClass}
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={t("accountMenu", { name })}
        onClick={() => setOpen((v) => !v)}
      >
        <span className="grid size-7 place-items-center rounded-full bg-primary-700 text-xs font-bold uppercase text-white" aria-hidden>
          {first.slice(0, 1)}
        </span>
        <span className="max-w-28 truncate max-sm:sr-only">{first}</span>
        <ChevronDown className={cn("size-4 transition-transform max-sm:hidden", open && "rotate-180")} aria-hidden />
      </button>
      {open && (
        <div id={menuId} className="absolute end-0 top-full z-50 mt-2 w-64 rounded-card border border-ink-100 bg-white p-2 shadow-raised animate-fade-in">
          <p className="px-3 pb-2 pt-1 text-xs text-ink-500">
            {t("signedInAs")}
            <span className="block truncate text-sm font-semibold text-ink-900">{user?.email ?? name}</span>
          </p>
          <ul className="border-t border-ink-100 pt-1">
            {items.map(({ href, label, Icon }) => (
              <li key={href}>
                <Link href={href} onClick={() => setOpen(false)} className="flex min-h-11 items-center gap-3 rounded-control px-3 text-ink-900 hover:bg-ink-100">
                  <Icon className="size-5 text-primary-700" aria-hidden />
                  {label}
                </Link>
              </li>
            ))}
            <li className="mt-1 border-t border-ink-100 pt-1">
              <button
                type="button"
                className="flex min-h-11 w-full items-center gap-3 rounded-control px-3 text-start text-ink-900 hover:bg-ink-100"
                onClick={async () => {
                  setOpen(false);
                  await signOut();
                  router.push("/");
                }}
              >
                <LogOut className="size-5 text-error" aria-hidden />
                {t("signOut")}
              </button>
            </li>
          </ul>
        </div>
      )}
    </div>
  );
}

/** Heart link to favourites with a count badge (Figma header). */
export function FavouritesLink() {
  const t = useTranslations("header");
  const count = useFavourites().list.length;
  return (
    <Link href="/account/favourites" className="relative grid size-11 place-items-center rounded-full text-ink-900 hover:bg-ink-100" aria-label={t("favouritesAria", { count })}>
      <Heart className="size-5" aria-hidden />
      {count > 0 && (
        <span className="absolute end-1 top-1 grid min-w-4 place-items-center rounded-pill bg-cta-600 px-1 text-[10px] font-bold leading-4 text-white" aria-hidden>
          {count}
        </span>
      )}
    </Link>
  );
}
