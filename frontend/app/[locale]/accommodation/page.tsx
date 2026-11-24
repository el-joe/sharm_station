import { AccommodationSearch, type AccommodationQuery } from "@/components/accommodation/AccommodationSearch";
import { getNationality } from "@/lib/api/nationality-server";
import { AccommodationResults } from "@/components/accommodation/AccommodationResults";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { mockApi } from "@/lib/api/mock-api";
import { getMeta, getRooms } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { nightsBetween } from "@/lib/utils";
import { BedDouble } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("accommodation");
  return pageMetadata({ locale, path: "/accommodation", title: t("metaTitle"), description: t("subtitle") });
}

type Search = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
const iso = (v?: string) => (v && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : undefined);

export default async function AccommodationPage({ searchParams }: PageProps<"/[locale]/accommodation">) {
  const locale = (await rootLocale()) as Locale;
  const [t, tc] = await Promise.all([getTranslations("accommodation"), getTranslations("common")]);
  return (
    <>
      <section className="bg-sand-50">
        <div className="container-page py-8 lg:py-12">
          <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: t("crumb") }]} />
          <h1 className="text-h1 mt-3 text-ink-900">{t("title")}</h1>
          <p className="mt-2 max-w-2xl text-ink-700 lg:text-lg">{t("subtitle")}</p>
        </div>
      </section>
      <div className="container-page py-8 lg:py-12">
        <Suspense fallback={<div className="h-[600px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
          <Results locale={locale} searchParams={searchParams} />
        </Suspense>
      </div>
    </>
  );
}

async function Results({ locale, searchParams }: { locale: Locale; searchParams: Search }) {
  const raw = await searchParams;
  const sort = one(raw.sort);
  const query: AccommodationQuery = {
    destination: one(raw.destination) || undefined,
    building: one(raw.building) || undefined,
    check_in: iso(one(raw.check_in)),
    check_out: iso(one(raw.check_out)),
    adults: Math.max(1, Number(one(raw.adults)) || 2),
    children: Math.max(0, Number(one(raw.children)) || 0),
    free_cancellation: one(raw.free_cancellation) === "1",
    sort: sort === "price_asc" || sort === "price_desc" || sort === "rating" ? sort : "recommended",
  };
  const [rooms, meta, t] = await Promise.all([getRooms(query, locale, await getNationality()), getMeta(locale), getTranslations("accommodation")]);
  const nights = query.check_in && query.check_out ? nightsBetween(query.check_in, query.check_out) : undefined;
  const buildings = Array.from(new Map(rooms.data.map((r) => [r.building.slug, { slug: r.building.slug, name: r.building.name }])).values());
  if (!buildings.length) buildings.push(...mockApi.rooms({}, locale).data.map((r) => ({ slug: r.building.slug, name: r.building.name })).filter((b, i, a) => a.findIndex((x) => x.slug === b.slug) === i));
  const linkQuery: Record<string, string> = {};
  if (query.check_in) linkQuery.check_in = query.check_in;
  if (query.check_out) linkQuery.check_out = query.check_out;
  linkQuery.adults = String(query.adults);
  if (query.children) linkQuery.children = String(query.children);

  return (
    <div className="flex flex-col gap-8">
      <AccommodationSearch query={query} destinations={meta.destinations} buildings={buildings} total={rooms.meta.total} />
      {rooms.data.length === 0 ? (
        <div className="flex flex-col items-center rounded-card border border-dashed border-ink-300 px-6 py-16 text-center">
          <BedDouble className="size-10 text-ink-500" aria-hidden />
          <h2 className="mt-4 text-xl font-semibold">{t("emptyTitle")}</h2>
          <p className="mt-1 text-ink-700">{t("emptyText")}</p>
        </div>
      ) : (
        <AccommodationResults rooms={rooms.data} nights={nights} query={linkQuery} initialView={one(raw.view) === "map" ? "map" : "list"} />
      )}
    </div>
  );
}
