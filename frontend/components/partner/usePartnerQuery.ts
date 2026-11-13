"use client";

import { ApiError, type RequestOptions } from "@/lib/api/client";
import type { Locale, Planned } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useQuery } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { useEffect } from "react";

/** Authenticated query for a planned partner endpoint; 401 signs the user out. */
export function usePartnerQuery<T>(key: unknown[], fn: (o: RequestOptions & { token: string }) => Promise<Planned<T>>) {
  const locale = useLocale() as Locale;
  const { token, signOut } = useAuth();
  const query = useQuery({
    queryKey: ["partner", ...key, token, locale],
    queryFn: ({ signal }) => fn({ locale, token: token!, signal }),
    enabled: Boolean(token),
    retry: false,
  });
  useEffect(() => {
    if (query.error instanceof ApiError && query.error.status === 401) void signOut();
  }, [query.error, signOut]);
  return query;
}
