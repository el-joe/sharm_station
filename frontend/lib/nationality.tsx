"use client";

/**
 * Client nationality state (SRS 3.9, D8).
 *
 * The choice lives in the `ssv_nationality` cookie (1 year, SameSite=Lax) so server components can
 * price with it, and in the signed-in user's account (PATCH /me/profile). Changing it refreshes the
 * server-rendered prices (router.refresh) and every client query (invalidation).
 */
import { useRouter } from "@/i18n/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useSyncExternalStore, useTransition, type ReactNode } from "react";
import { api } from "./api/client";
import type { Country, Locale, Nationalities } from "./api/types";
import { useAuth } from "./auth";
import { NATIONALITY_COOKIE, NATIONALITY_MAX_AGE, normalizeNationality } from "./nationality-shared";

const EVENT = "ssv:nationality";

function readCookie(): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${NATIONALITY_COOKIE}=([^;]+)`));
  return normalizeNationality(match?.[1]);
}

function writeCookie(code: string) {
  const secure = window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${NATIONALITY_COOKIE}=${code}; path=/; max-age=${NATIONALITY_MAX_AGE}; SameSite=Lax${secure}`;
  window.dispatchEvent(new Event(EVENT));
}

function subscribe(cb: () => void) {
  window.addEventListener(EVENT, cb);
  window.addEventListener("focus", cb);
  return () => {
    window.removeEventListener(EVENT, cb);
    window.removeEventListener("focus", cb);
  };
}

interface NationalityContextValue {
  /** undefined while hydrating (unknown), null when the visitor has not chosen yet. */
  code: string | null | undefined;
  country: Country | null;
  data: Nationalities | undefined;
  loading: boolean;
  /** True while the refreshed prices are being rendered. */
  refreshing: boolean;
  setNationality: (code: string) => Promise<void>;
}

const NationalityContext = createContext<NationalityContextValue | null>(null);

export function NationalityProvider({ children }: { children: ReactNode }) {
  const locale = useLocale() as Locale;
  const router = useRouter();
  const queryClient = useQueryClient();
  const { user, token, setUser } = useAuth();
  const [refreshing, startTransition] = useTransition();
  const code = useSyncExternalStore<string | null | undefined>(subscribe, readCookie, () => undefined);

  const query = useQuery({
    queryKey: ["nationalities", locale],
    queryFn: ({ signal }) => api.nationalities({ locale, signal }),
    staleTime: 24 * 60 * 60_000,
    retry: 2,
  });

  const refreshPrices = useCallback(() => {
    // Every query except the static ones may carry a price for the old nationality.
    void queryClient.invalidateQueries({ predicate: (q) => !["auth", "nationalities"].includes(String(q.queryKey[0])) });
    startTransition(() => router.refresh());
  }, [queryClient, router]);

  const saveToAccount = useCallback(
    async (next: string) => {
      if (!token) return;
      try {
        const res = await api.updateProfile({ nationality_code: next }, { locale, token });
        if (res.status === "ok") setUser(res.data);
      } catch {
        /* the cookie is the source of truth for prices; the account copy is best effort */
      }
    },
    [locale, token, setUser],
  );

  const setNationality = useCallback(
    async (next: string) => {
      const normalized = normalizeNationality(next);
      if (!normalized) return;
      const changed = normalized !== readCookie();
      writeCookie(normalized);
      if (changed) refreshPrices();
      if (user && user.nationality_code !== normalized) await saveToAccount(normalized);
    },
    [refreshPrices, saveToAccount, user],
  );

  // Keep cookie and account in step when a signed-in user is known (sign-in or session load):
  // the account's nationality wins and overwrites the cookie; an account without one gets the cookie value.
  const synced = useRef<number | null>(null);
  useEffect(() => {
    if (!user) {
      synced.current = null; // signed out: sync again at the next sign-in
      return;
    }
    if (code === undefined || synced.current === user.id) return;
    synced.current = user.id;
    const account = normalizeNationality(user.nationality_code);
    if (account && account !== code) {
      writeCookie(account);
      refreshPrices();
    } else if (code && !account) {
      void saveToAccount(code);
    }
  }, [user, code, refreshPrices, saveToAccount]);

  const value = useMemo<NationalityContextValue>(
    () => ({
      code,
      country: (code && query.data?.countries.find((c) => c.code === code)) || null,
      data: query.data,
      loading: query.isPending,
      refreshing,
      setNationality,
    }),
    [code, query.data, query.isPending, refreshing, setNationality],
  );

  return <NationalityContext.Provider value={value}>{children}</NationalityContext.Provider>;
}

export function useNationality() {
  const ctx = useContext(NationalityContext);
  if (!ctx) throw new Error("useNationality must be used inside <NationalityProvider>");
  return ctx;
}

/** The nationality to send with a priced request (null until chosen: the API then uses the default group). */
export function useNationalityCode(): string | null {
  return useNationality().code ?? null;
}
