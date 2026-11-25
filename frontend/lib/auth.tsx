"use client";

/**
 * Client auth state (F6: cookie session).
 *
 * The Sanctum token is kept by the Next route handlers in the httpOnly cookie `ssv_session`
 * (app/api/auth/*, lib/server/session.ts) and is never visible to JavaScript. Authenticated API
 * calls go through /api/proxy with `auth: true` (lib/api/client.ts). The readable cookie
 * `ssv_signed_in=1` is only a hint that a session exists, so anonymous visitors never call
 * /api/auth/me; the user itself comes from GET /api/auth/me (TanStack Query), and a 401 clears
 * the session.
 *
 * Migration: a pre-F6 token still in localStorage (`ssv_token`) is exchanged once for the cookie
 * through POST /api/auth/migrate and removed from localStorage either way.
 */
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { createContext, useCallback, useContext, useEffect, useMemo, useSyncExternalStore, type ReactNode } from "react";
import { api, ApiError } from "./api/client";
import type { AuthUser, Locale } from "./api/types";
import { LEGACY_TOKEN_KEY, SESSION_HINT_COOKIE } from "./session-shared";

const AUTH_EVENT = "ssv:auth";
const HINT = new RegExp(`(?:^|;\\s*)${SESSION_HINT_COOKIE}=1(?:;|$)`);

function readHint(): boolean {
  return HINT.test(document.cookie);
}

function notify() {
  window.dispatchEvent(new Event(AUTH_EVENT));
}

function subscribe(cb: () => void) {
  // Other tabs: there is no cookie change event everywhere, so re-check when the tab is focused again.
  const onVisible = () => document.visibilityState === "visible" && cb();
  window.addEventListener(AUTH_EVENT, cb);
  window.addEventListener("focus", cb);
  document.addEventListener("visibilitychange", onVisible);
  return () => {
    window.removeEventListener(AUTH_EVENT, cb);
    window.removeEventListener("focus", cb);
    document.removeEventListener("visibilitychange", onVisible);
  };
}

function readLegacyToken(): string | null {
  try {
    return window.localStorage.getItem(LEGACY_TOKEN_KEY);
  } catch {
    return null;
  }
}

function dropLegacyToken() {
  try {
    window.localStorage.removeItem(LEGACY_TOKEN_KEY);
  } catch {
    /* storage unavailable */
  }
}

/** True while a legacy token waits for (or is in) the exchange: keeps the status "loading" meanwhile. */
let migrationInFlight = false;
const readMigrating = () => migrationInFlight || readLegacyToken() !== null;

/** "loading" during SSR/hydration and while /auth/me is pending. */
export type AuthStatus = "loading" | "authenticated" | "anonymous";

interface AuthContextValue {
  status: AuthStatus;
  /** True while a session cookie exists (also before /auth/me answered): gate authenticated queries on it. */
  hasSession: boolean;
  /** null when signed out, or when /auth/me could not be reached (API down). */
  user: AuthUser | null;
  isPartner: boolean;
  isActivePartner: boolean;
  /** Call after /api/auth/login|register set the cookie. */
  signIn: (user?: AuthUser) => void;
  signOut: () => Promise<void>;
  setUser: (user: AuthUser) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);
const ME_KEY = ["auth", "me"] as const;

export function AuthProvider({ children }: { children: ReactNode }) {
  const locale = useLocale() as Locale;
  const queryClient = useQueryClient();
  // undefined = server snapshot (unknown).
  const hint = useSyncExternalStore<boolean | undefined>(subscribe, readHint, () => undefined);
  const migrating = useSyncExternalStore(subscribe, readMigrating, () => false);

  // One-time exchange of a legacy localStorage token for the cookie (removed from localStorage either way).
  useEffect(() => {
    if (migrationInFlight) return;
    const legacy = readLegacyToken();
    if (!legacy) return;
    migrationInFlight = true;
    dropLegacyToken();
    api
      .migrateSession(legacy, { locale })
      .then((user) => queryClient.setQueryData(ME_KEY, user))
      .catch(() => {
        /* expired or revoked: the visitor simply signs in again */
      })
      .finally(() => {
        migrationInFlight = false;
        notify();
      });
  }, [locale, queryClient]);

  const me = useQuery({
    queryKey: ME_KEY,
    queryFn: ({ signal }) => api.me({ locale, signal }),
    enabled: Boolean(hint),
    retry: false,
    staleTime: 5 * 60_000,
  });

  const unauthorized = me.error instanceof ApiError && me.error.status === 401;
  useEffect(() => {
    // The handler already cleared the cookies with its 401; re-read the hint.
    if (unauthorized) {
      queryClient.removeQueries({ queryKey: ME_KEY });
      notify();
    }
  }, [unauthorized, queryClient]);

  const signIn = useCallback(
    (user?: AuthUser) => {
      if (user) queryClient.setQueryData(ME_KEY, user);
      else void queryClient.invalidateQueries({ queryKey: ME_KEY });
      notify();
    },
    [queryClient],
  );

  const signOut = useCallback(async () => {
    await api.logout({ locale });
    queryClient.removeQueries({ queryKey: ["auth"] });
    queryClient.removeQueries({ queryKey: ["account"] });
    queryClient.removeQueries({ queryKey: ["partner"] });
    notify();
  }, [locale, queryClient]);

  const setUser = useCallback((user: AuthUser) => queryClient.setQueryData(ME_KEY, user), [queryClient]);

  const value = useMemo<AuthContextValue>(() => {
    const hasSession = Boolean(hint) && !unauthorized;
    const status: AuthStatus = hint === undefined || migrating || (hasSession && me.isPending) || (hint && unauthorized) ? "loading" : hasSession ? "authenticated" : "anonymous";
    const user = status === "authenticated" ? (me.data ?? null) : null;
    const isPartner = user?.role === "partner" || Boolean(user?.partner);
    return {
      status,
      hasSession,
      user,
      isPartner,
      isActivePartner: isPartner && user?.partner?.status === "active",
      signIn,
      signOut,
      setUser,
    };
  }, [hint, migrating, me.isPending, me.data, unauthorized, signIn, signOut, setUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}

/** Only same-site, locale-less paths are accepted as a post-login destination. */
export function safeReturnTo(value: string | null | undefined): string | null {
  if (!value || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) return null;
  return value.replace(/^\/(en|it)(?=\/|$)/, "") || "/";
}
