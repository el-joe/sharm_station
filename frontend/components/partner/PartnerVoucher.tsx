"use client";

import { Button } from "@/components/ui/Button";
import { Logo } from "@/components/ui/Logo";
import { api } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { useLocationSearch } from "@/lib/hooks";
import { CONTACT_EMAIL, PHONE_DISPLAY } from "@/lib/site";
import { parseISODate } from "@/lib/utils";
import { Printer } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { use } from "react";
import { useServiceLabel } from "./PartnerBookings";
import { PartnerData } from "./PartnerStates";
import { CountryLabel } from "./shared";
import { usePartnerQuery } from "./usePartnerQuery";

const PRINT_CSS = `@media print {
  body * { visibility: hidden !important; }
  #ssv-voucher, #ssv-voucher * { visibility: visible !important; }
  #ssv-voucher { position: absolute; inset: 0; box-shadow: none; border: 0; }
  .voucher-actions { display: none !important; }
}`;

/**
 * Printable client voucher, with or without prices (SRS 8.3: "A partner can print a client voucher without prices").
 * Prices shown "with prices" are the customer prices the client pays, never the partner price.
 */
export function PartnerVoucher({ params }: { params: Promise<{ reference: string }> }) {
  const { reference } = use(params);
  const t = useTranslations("partnerPanel.voucher");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const service = useServiceLabel();
  const withPrices = new URLSearchParams(useLocationSearch()).get("prices") === "1";
  const query = usePartnerQuery(["booking", reference], (o) => api.partnerBooking(reference, o));
  const date = (d: string | null | undefined) => (d ? format.dateTime(parseISODate(d.slice(0, 10)), { weekday: "short", day: "numeric", month: "long", year: "numeric" }) : "");

  return (
    <PartnerData query={query} skeleton="h-[600px]">
      {(b) =>
        !b ? (
          <p className="rounded-card bg-white p-6 text-center">{t("notFound")}</p>
        ) : (
          <>
            <style>{PRINT_CSS}</style>
            <div className="voucher-actions mb-4 flex flex-wrap items-center justify-between gap-3">
              <p className="text-sm text-ink-700">{withPrices ? t("withPrices") : t("withoutPrices")}</p>
              <Button variant="partner" icon={<Printer className="size-5" aria-hidden />} onClick={() => window.print()}>
                {t("print")}
              </Button>
            </div>
            <article id="ssv-voucher" className="rounded-card border border-ink-100 bg-white p-6 shadow-card md:p-10" aria-labelledby="voucher-title">
              <header className="flex flex-wrap items-start justify-between gap-4 border-b border-ink-100 pb-5">
                <Logo />
                <div className="text-end">
                  <p className="text-xs uppercase tracking-wide text-ink-500">{t("label")}</p>
                  <h1 id="voucher-title" className="text-2xl font-bold text-ink-900">
                    {b.reference}
                  </h1>
                </div>
              </header>
              <dl className="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                <Item label={t("service")} value={`${b.product_title} · ${service(b.service_type)}`} />
                <Item label={t("date")} value={b.check_in ? `${date(b.check_in)} – ${date(b.check_out)}` : b.transfer ? b.transfer.pickup_at : date(b.travel_date)} />
                <Item label={t("client")} value={b.lead.name} />
                <Item label={t("nationality")} value={<CountryLabel code={b.nationality.code} />} />
                <Item label={t("travellers")} value={t("party", { adults: b.adults, children: b.children })} />
                {b.transfer && <Item label={t("destination")} value={b.transfer.destination.name} />}
              </dl>
              {b.travellers.length > 0 && (
                <section className="mt-6">
                  <h2 className="text-sm font-semibold text-ink-900">{t("names")}</h2>
                  <ul className="mt-2 list-inside list-disc text-sm text-ink-700">
                    {b.travellers.map((tr, i) => (
                      <li key={i}>
                        {tr.first_name} {tr.last_name}
                        {tr.type === "child" && tr.age != null ? ` (${tr.age})` : ""}
                      </li>
                    ))}
                  </ul>
                </section>
              )}
              {withPrices && (
                <section className="mt-6">
                  <h2 className="text-sm font-semibold text-ink-900">{t("prices")}</h2>
                  <table className="mt-2 w-full text-sm">
                    <tbody className="divide-y divide-ink-100">
                      {b.lines.map((l, i) => (
                        <tr key={i}>
                          <th scope="row" className="py-2 text-start font-normal text-ink-700">
                            {l.label} × {l.quantity}
                          </th>
                          <td className="py-2 text-end text-ink-900">{money(l.total, { decimals: true })}</td>
                        </tr>
                      ))}
                      <tr>
                        <th scope="row" className="py-2 text-start font-semibold text-ink-900">
                          {t("total")}
                        </th>
                        <td className="py-2 text-end font-bold text-ink-900">{money(b.totals.retail_total, { decimals: true })}</td>
                      </tr>
                    </tbody>
                  </table>
                </section>
              )}
              <footer className="mt-8 border-t border-ink-100 pt-4 text-xs text-ink-500">
                {t("footer", { phone: PHONE_DISPLAY, email: CONTACT_EMAIL })}
              </footer>
            </article>
          </>
        )
      }
    </PartnerData>
  );
}

function Item({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-ink-500">{label}</dt>
      <dd className="font-medium text-ink-900">{value}</dd>
    </div>
  );
}
