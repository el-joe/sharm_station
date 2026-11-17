"use client";

import { ApiError, type RequestOptions } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useQuery } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { useEffect } from "react";

/** Authenticated partner-panel query; 401 signs the user out, 403 (not an active partner / disabled) is shown by PartnerData. */
export function usePartnerQuery<T>(key: unknown[], fn: (o: RequestOptions & { token: string }) => Promise<T>, enabled = true) {
  const locale = useLocale() as Locale;
  const { token, signOut } = useAuth();
  const query = useQuery({
    queryKey: ["partner", ...key, token, locale],
    queryFn: ({ signal }) => fn({ locale, token: token!, signal }),
    enabled: Boolean(token) && enabled,
    retry: (count, error) => !(error instanceof ApiError && [401, 403, 404, 422].includes(error.status)) && count < 1,
  });
  useEffect(() => {
    if (query.error instanceof ApiError && query.error.status === 401) void signOut();
  }, [query.error, signOut]);
  return query;
}
