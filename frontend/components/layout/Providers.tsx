"use client";

import type { Currency } from "@/lib/api/types";
import { AuthProvider } from "@/lib/auth";
import { CurrencyProvider } from "@/lib/currency";
import { NationalityProvider } from "@/lib/nationality";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

export function Providers({ currencies, children }: { currencies: Currency[]; children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { staleTime: 60_000, refetchOnWindowFocus: false, retry: 1 },
        },
      }),
  );
  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <NationalityProvider>
          <CurrencyProvider currencies={currencies}>{children}</CurrencyProvider>
        </NationalityProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
