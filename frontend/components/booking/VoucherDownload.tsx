"use client";

import { buttonClasses } from "@/components/ui/Button";
import { PROXY_PREFIX } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { PUBLIC_API_URL } from "@/lib/site";
import { cn } from "@/lib/utils";
import { FileDown } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import type { ReactNode } from "react";

/**
 * GET /bookings/{reference}/voucher.pdf?prices=0|1&locale=[&token=] (owner, partner, staff or booking-link holder).
 * Signed-in visitors go through the same-origin /api/proxy (session cookie -> Bearer, Content-Disposition passed on);
 * guests open the API directly with the booking access token. The API answers 409 before the deposit is paid, so
 * callers only render this when the booking says `voucher_available`.
 */
export function voucherUrl(reference: string, opts: { locale: Locale; prices: boolean; auth?: boolean; accessToken?: string | null }) {
  const search = new URLSearchParams({ prices: opts.prices ? "1" : "0", locale: opts.locale });
  if (opts.accessToken) search.set("token", opts.accessToken);
  const path = `/bookings/${encodeURIComponent(reference)}/voucher.pdf?${search}`;
  return opts.auth ? `${PROXY_PREFIX}${path}` : `${PUBLIC_API_URL}${path}`;
}

/**
 * Voucher download card. `variants`: "prices" (customer copy with prices) and/or "client" (partner's client copy,
 * no prices). The end client of a partner booking never gets prices from the API, so pass `["client"]` there.
 */
export function VoucherDownload({
  reference,
  accessToken,
  auth = false,
  variants = ["prices"],
  className,
  tone = "outline",
  children,
}: {
  reference: string;
  accessToken?: string | null;
  auth?: boolean;
  variants?: ("prices" | "client")[];
  className?: string;
  tone?: "outline" | "partner" | "cta";
  /** Extra content under the buttons (e.g. the printable version links). */
  children?: ReactNode;
}) {
  const t = useTranslations("voucherDownload");
  const locale = useLocale() as Locale;
  const single = variants.length === 1;

  return (
    <section className={cn("rounded-card border border-ink-100 bg-white p-5 shadow-card", className)} aria-label={t("title")}>
      <h2 className="text-h4 text-ink-900">{t("title")}</h2>
      <p className="mt-1 text-sm text-ink-500">{variants.includes("client") && !single ? t("clientCopyHint") : t("text")}</p>
      <div className="mt-4 flex flex-col gap-3">
        {variants.map((v, i) => (
          <a
            key={v}
            href={voucherUrl(reference, { locale, prices: v === "prices", auth, accessToken })}
            download
            rel="nofollow"
            className={buttonClasses(i === 0 ? tone : "outline", "md", "w-full")}
            data-voucher={v}
          >
            <FileDown className="size-5" aria-hidden />
            {single ? t("download") : v === "prices" ? t("withPrices") : t("clientCopy")}
          </a>
        ))}
        {children}
      </div>
    </section>
  );
}
