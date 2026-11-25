"use client";

import { usePathname } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth";
import { useNationality } from "@/lib/nationality";
import { useSyncExternalStore } from "react";
import { NationalityDialog } from "./NationalityDialog";

/**
 * Pages that never show the gate: the partner panel (partners pick the client's nationality per booking),
 * the account entry pages (a signed-in account brings its own nationality; ?returnTo is kept as is),
 * the partner application and the legal texts the gate links to.
 */
const EXEMPT = [/^\/partner(\/|$)/, /^\/sign-in$/, /^\/sign-up$/, /^\/forgot-password$/, /^\/reset-password$/, /^\/become-a-partner$/, /^\/privacy$/, /^\/cookie-policy$/];

const BOT = /bot|crawl|spider|slurp|bingpreview|lighthouse|facebookexternalhit|embedly|whatsapp/i;
const noop = () => () => {};

/**
 * Mandatory first-visit nationality modal (D8). Client-only and rendered after hydration, so the
 * server-rendered page (and what crawlers index) is untouched; known crawlers never see it.
 */
export function NationalityGate() {
  const pathname = usePathname();
  const { code } = useNationality();
  const { status, user } = useAuth();
  const isBot = useSyncExternalStore(noop, () => BOT.test(navigator.userAgent), () => true);

  // Wait for hydration (code === undefined) and auth: a signed-in user's saved nationality fills the cookie.
  const waiting = code === undefined || status === "loading" || (status === "authenticated" && Boolean(user?.nationality_code));
  const open = !waiting && code === null && !isBot && !EXEMPT.some((r) => r.test(pathname));

  return <NationalityDialog open={open} mode="required" onDone={() => {}} />;
}
