"use client";

/**
 * Client auth state.
 *
 * The Sanctum personal access token returned by POST /auth/login|register is kept
 * in localStorage (`ssv_token`) and sent as a Bearer header. The API lives on
 * another origin (:8090) and every catalogue call goes straight to it, so moving
 * the token into an httpOnly cookie would need a Next route-handler proxy for
 * every authenticated call; that is planned but not done (see docs/BACKLOG.md).
 *
 * The user comes from GET /auth/me (TanStack Query); a 401 drops the token.
 */
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { createContext, useCallback, useContext, useEffect, useMemo, useSyncExternalStore, type ReactNode } from "react";
import { api, ApiError } from "./api/client";
import type { AuthUser, Locale } from "./api/types";

export const TOKEN_KEY = "ssv_token";
const AUTH_EVENT = "ssv:auth";

function readToken(): string | null {
  try {
    return window.localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

function writeToken(token: string | null) {
  try {
    if (token) window.localStorage.setItem(TOKEN_KEY, token);
    else window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* storage unavailable (private mode) */
  }
  window.dispatchEvent(new Event(AUTH_EVENT));
}

function subscribe(cb: () => void) {
  const onStorage = (e: StorageEvent) => e.key === TOKEN_KEY && cb();
  window.addEventListener(AUTH_EVENT, cb);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(AUTH_EVENT, cb);
    window.removeEventListener("storage", onStorage);
  };
}

/** "loading" during SSR/hydration and while /auth/me is pending. */
export type AuthStatus = "loading" | "authenticated" | "anonymous";

interface AuthContextValue {
  status: AuthStatus;
  token: string | null;
  /** null when signed out, or when /auth/me could not be reached (API down). */
  user: AuthUser | null;
  isPartner: boolean;
  isActivePartner: boolean;
  signIn: (token: string, user?: AuthUser) => void;
  signOut: () => Promise<void>;
  setUser: (user: AuthUser) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const locale = useLocale() as Locale;
  const queryClient = useQueryClient();
  // undefined = server snapshot (unknown), null = no token.
  const token = useSyncExternalStore<string | null | undefined>(subscribe, readToken, () => undefined);

  const me = useQuery({
    queryKey: ["auth", "me", token],
    queryFn: ({ signal }) => api.me({ locale, token: token!, signal }),
    enabled: Boolean(token),
    retry: false,
    staleTime: 5 * 60_000,
  });

  const unauthorized = me.error instanceof ApiError && me.error.status === 401;
  useEffect(() => {
    if (unauthorized) writeToken(null);
  }, [unauthorized]);

  const signIn = useCallback(
    (next: string, user?: AuthUser) => {
      if (user) queryClient.setQueryData(["auth", "me", next], user);
      writeToken(next);
    },
    [queryClient],
  );

  const signOut = useCallback(async () => {
    const current = readToken();
    writeToken(null);
    queryClient.removeQueries({ queryKey: ["auth"] });
    queryClient.removeQueries({ queryKey: ["account"] });
    queryClient.removeQueries({ queryKey: ["partner"] });
    if (current) await api.logout({ locale, token: current });
  }, [locale, queryClient]);

  const setUser = useCallback((user: AuthUser) => queryClient.setQueryData(["auth", "me", token], user), [queryClient, token]);

  const value = useMemo<AuthContextValue>(() => {
    const status: AuthStatus = token === undefined || (token && me.isPending) || unauthorized ? "loading" : token ? "authenticated" : "anonymous";
    const user = status === "authenticated" ? (me.data ?? null) : null;
    const isPartner = user?.role === "partner" || Boolean(user?.partner);
    return {
      status,
      token: token ?? null,
      user,
      isPartner,
      isActivePartner: isPartner && user?.partner?.status === "active",
      signIn,
      signOut,
      setUser,
    };
  }, [token, me.isPending, me.data, unauthorized, signIn, signOut, setUser]);

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
