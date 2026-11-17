"use client";

import { NationalityCombobox } from "@/components/nationality/NationalityCombobox";
import { Field, Input, Select } from "@/components/ui/Field";
import { api } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { useNationality } from "@/lib/nationality";
import { Search } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useState } from "react";
import { PartnerData } from "./PartnerStates";
import { CommissionPill, PartnerPageHeader, SERVICES, useCommissionSource } from "./shared";
import { useClientNationality } from "./useClientNationality";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner/price-list (Figma 256:35682): customer and partner prices for a chosen client nationality (403 when not enabled). */
export function PartnerPriceList() {
  const t = useTranslations("partnerPanel.priceList");
  const ts = useTranslations("partnerPanel.services");
  const { data } = useNationality();
  const { format } = useCurrency();
  const formatter = useFormatter();
  const range = (from: string, to: string) => {
    const [a, b] = [from, to].map((d) => new Date(`${d}T00:00:00`));
    return formatter.dateTimeRange(a, b, { day: "numeric", month: "short", year: "numeric" });
  };
  const source = useCommissionSource();
  const [nationality, setNationality] = useClientNationality();
  const [service, setService] = useState("");
  const [q, setQ] = useState("");
  const query = usePartnerQuery(["price-list", nationality, service], (o) => api.partnerPriceList({ nationality: nationality ?? "", service: service || undefined }, o), Boolean(nationality));
  const group = data?.countries.find((c) => c.code === nationality)?.group;

  return (
    <div className="flex flex-col gap-6">
      <PartnerPageHeader crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      <div className="grid grid-cols-1 gap-4 rounded-card bg-partner-100 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-start">
        <div className="flex flex-col gap-1">
          <NationalityCombobox
            id="pl-nationality"
            label={t("nationality")}
            countries={data?.countries ?? []}
            value={nationality}
            onChange={(c) => c && setNationality(c)}
            suggestions={[nationality]}
            required
          />
          {group && <p className="text-xs text-partner-600">{t("group", { group: group.name })}</p>}
        </div>
        <Field label={t("service")} htmlFor="pl-service">
          <Select id="pl-service" value={service} onChange={(e) => setService(e.target.value)}>
            <option value="">{t("allServices")}</option>
            {SERVICES.map((s) => (
              <option key={s} value={s}>
                {ts(s)}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("search")} htmlFor="pl-search">
          <div className="relative">
            <Search className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-ink-500" aria-hidden />
            <Input id="pl-search" type="search" className="ps-10" value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
        </Field>
      </div>
      {!nationality ? (
        <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("chooseNationality")}</p>
      ) : (
        <PartnerData query={query} forbiddenText={t("disabled")}>
          {(rows) => {
            const shown = rows.filter((r) => r.title.toLowerCase().includes(q.trim().toLowerCase()));
            if (!shown.length) return <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("empty")}</p>;
            return (
              <div className="overflow-x-auto rounded-card border border-ink-100 bg-white shadow-card">
                <table className="w-full min-w-[720px] text-sm">
                  <caption className="sr-only">{t("caption", { group: group?.name ?? "" })}</caption>
                  <thead>
                    <tr className="bg-sand-50 text-xs uppercase tracking-wide text-ink-500">
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.product")}</th>
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.season")}</th>
                      <th scope="col" className="px-4 py-3 text-end font-semibold">{t("columns.retail")}</th>
                      <th scope="col" className="px-4 py-3 text-start font-semibold">{t("columns.commission")}</th>
                      <th scope="col" className="px-4 py-3 text-end font-semibold">{t("columns.partner")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100">
                    {shown.map((r, i) => (
                      <tr key={`${r.service}-${r.slug ?? r.title}-${i}`}>
                        <th scope="row" className="px-4 py-3 text-start">
                          <span className="block font-semibold text-ink-900">{r.title}</span>
                          <span className="text-xs font-normal text-ink-500">{SERVICES.includes(r.service as never) ? ts(r.service as "packages") : r.service}</span>
                        </th>
                        <td className="px-4 py-3 text-ink-700">
                          {r.row_type === "season" ? (
                            <>
                              <span className="block font-medium text-ink-900">{r.season}</span>
                              {r.starts_on && r.ends_on && <span className="text-xs text-ink-500">{range(r.starts_on, r.ends_on)}</span>}
                            </>
                          ) : r.row_type === "from" ? (
                            t("from")
                          ) : (
                            t("basePrice")
                          )}
                        </td>
                        <td className="px-4 py-3 text-end text-ink-500">
                          <s>{format(r.retail_price, { decimals: true })}</s>
                        </td>
                        <td className="px-4 py-3">
                          <CommissionPill percent={r.commission.percent} />
                          {r.commission.percent > 0 && <span className="ms-2 text-xs text-ink-500">{source(r.commission.source)}</span>}
                        </td>
                        <td className="px-4 py-3 text-end text-base font-bold text-partner-600">{format(r.partner_price, { decimals: true })}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            );
          }}
        </PartnerData>
      )}
    </div>
  );
}
