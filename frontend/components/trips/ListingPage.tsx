import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { getNationality } from "@/lib/api/nationality-server";
import { getMeta, getPackages, getTrips } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { SearchX } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { ListingShell } from "./ListingShell";
import { Pagination } from "./Pagination";
import { TripCard } from "./TripCard";
import { parseListingState, toApiFilters, toQuery, type ListingKind } from "./filters";

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const PATHS: Record<ListingKind, string> = { trips: "/trips", nile: "/nile", packages: "/packages" };

/** Trips / Nile / Packages listing (SRS 13.1, 13.2: no date filter). */
export async function ListingPage({ kind, locale, searchParams }: { kind: ListingKind; locale: Locale; searchParams: SearchParams }) {
  const t = await getTranslations(`listing.${kind}`);
  const tc = await getTranslations("common");
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
        <Suspense fallback={<ListingSkeleton />}>
          <ListingResults kind={kind} locale={locale} searchParams={searchParams} />
        </Suspense>
      </div>
    </>
  );
}

async function ListingResults({ kind, locale, searchParams }: { kind: ListingKind; locale: Locale; searchParams: SearchParams }) {
  const state = parseListingState(await searchParams);
  const filters = toApiFilters(kind, state);
  const nationality = await getNationality();
  const [result, meta, t] = await Promise.all([
    kind === "packages" ? getPackages(filters, locale, nationality) : getTrips(filters, locale, nationality),
    getMeta(locale),
    getTranslations("filters"),
  ]);
  return (
    <ListingShell kind={kind} state={state} total={result.meta.total} destinations={meta.destinations}>
      {result.data.length === 0 ? (
        <div className="flex flex-col items-center rounded-card border border-dashed border-ink-300 px-6 py-16 text-center">
          <SearchX className="size-10 text-ink-500" aria-hidden />
          <h2 className="mt-4 text-xl font-semibold text-ink-900">{t("emptyTitle")}</h2>
          <p className="mt-1 text-ink-700">{t("emptyText")}</p>
        </div>
      ) : (
        <ul className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
          {result.data.map((trip, i) => (
            <li key={trip.slug}>
              <TripCard trip={trip} className="h-full" priority={i < 3} />
            </li>
          ))}
        </ul>
      )}
      <Pagination page={result.meta.current_page} lastPage={result.meta.last_page} pathname={PATHS[kind]} query={toQuery({ ...state, page: 1 })} />
    </ListingShell>
  );
}

function ListingSkeleton() {
  return (
    <div className="grid gap-8 lg:grid-cols-[280px_1fr]" aria-hidden>
      <div className="hidden h-[520px] animate-pulse rounded-card bg-ink-100 lg:block" />
      <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: 6 }, (_, i) => (
          <div key={i} className="h-[440px] animate-pulse rounded-card bg-ink-100" />
        ))}
      </div>
    </div>
  );
}
