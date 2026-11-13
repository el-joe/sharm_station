"use client";

import { Input } from "@/components/ui/Field";
import { api } from "@/lib/api/client";
import { Price } from "@/lib/currency";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { PartnerData } from "./PartnerStates";
import { PartnerPricePill } from "./PartnerPricePill";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner/price-list: read-only partner prices (GET /partner/price-list; 403 when not enabled by the admin). */
export function PartnerPriceList() {
  const t = useTranslations("partnerPanel.priceList");
  const ts = useTranslations("service");
  const [q, setQ] = useState("");
  const query = usePartnerQuery(["price-list"], api.partnerPriceList);

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-h2 text-ink-900">{t("title")}</h1>
        <p className="mt-1 text-ink-700">{t("subtitle")}</p>
      </div>
      <PartnerData query={query} forbiddenText={t("empty")}>
        {(rows) => {
          const shown = rows.filter((r) => r.title.toLowerCase().includes(q.trim().toLowerCase()));
          if (!rows.length) return <p className="rounded-card bg-sand-50 p-6 text-center text-ink-700">{t("empty")}</p>;
          return (
            <>
              <div className="relative max-w-md">
                <label htmlFor="pl-search" className="sr-only">{t("search")}</label>
                <Search className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-ink-500" aria-hidden />
                <Input id="pl-search" type="search" className="ps-10" placeholder={t("search")} value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
              <div className="overflow-x-auto rounded-card border border-ink-100 bg-white shadow-card">
                <table className="w-full min-w-[720px] text-sm">
                  <thead>
                    <tr className="bg-sand-50 text-ink-700">
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.product")}</th>
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.service")}</th>
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.season")}</th>
                      <th scope="col" className="px-4 py-3 text-end font-semibold">{t("columns.retail")}</th>
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.partner")}</th>
                      <th scope="col" className="px-4 py-3 text-end font-semibold">{t("columns.saving")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100">
                    {shown.map((r, i) => (
                      <tr key={`${r.slug}-${r.season}-${i}`}>
                        <th scope="row" className="px-4 py-3 text-start font-semibold text-ink-900">{r.title}</th>
                        <td className="px-4 py-3">{["trip", "nile", "package", "accommodation", "room", "transfer"].includes(r.service_type) ? ts(r.service_type as "trip") : r.service_type}</td>
                        <td className="px-4 py-3">{r.season ?? "–"}</td>
                        <td className="px-4 py-3 text-end text-ink-500"><Price amount={r.retail_price} /></td>
                        <td className="px-4 py-3"><PartnerPricePill amount={r.partner_price} /></td>
                        <td className="px-4 py-3 text-end font-semibold text-success">
                          {r.retail_price > 0 ? `${Math.round(((r.retail_price - r.partner_price) / r.retail_price) * 100)}%` : "–"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          );
        }}
      </PartnerData>
    </div>
  );
}
