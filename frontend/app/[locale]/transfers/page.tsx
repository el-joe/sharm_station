import { TransferMiniForm } from "@/components/home/TransferMiniForm";
import { AppImage } from "@/components/ui/AppImage";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { getTransferCatalog } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { Clock, Plane, ShieldCheck, UserRoundCheck } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";


/** Coordinates passed from the home search (?to_lat=&to_lng=) when a suggestion was picked. */
function toPoint(lat?: string, lng?: string) {
  const la = Number(lat);
  const ln = Number(lng);
  return lat && lng && Number.isFinite(la) && Number.isFinite(ln) ? { lat: la, lng: ln } : null;
}

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("transfers");
  return pageMetadata({ locale, path: "/transfers", title: t("metaTitle"), description: t("subtitle"), image: "/images/airport.webp" });
}

const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export default async function TransfersPage({ searchParams }: PageProps<"/[locale]/transfers">) {
  const [t, tc] = await Promise.all([getTranslations("transfers"), getTranslations("common")]);
  const catalog = await getTransferCatalog((await rootLocale()) as Locale);
  const perks = [
    { Icon: UserRoundCheck, text: t("perks.meet") },
    { Icon: Clock, text: t("perks.waiting") },
    { Icon: Plane, text: t("perks.flight") },
    { Icon: ShieldCheck, text: t("perks.fixed") },
  ];
  return (
    <>
      <section className="bg-sand-50">
        <div className="container-page py-8 lg:py-12">
          <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: t("crumb") }]} />
          <h1 className="text-h1 mt-3 text-ink-900">{t("title")}</h1>
          <p className="mt-2 max-w-2xl text-ink-700 lg:text-lg">{t("subtitle")}</p>
        </div>
      </section>
      <div className="container-page flex flex-col gap-6 py-8 lg:py-12">
        {/* CR-3 (revised): image first, then the form */}
        <div className="relative h-[220px] overflow-hidden rounded-image md:h-[360px]">
          <AppImage src="/images/airport.webp" alt={t("imageAlt")} fill sizes="(min-width: 1248px) 1200px, 100vw" className="object-cover" />
        </div>
        <Suspense fallback={<TransferMiniForm catalog={catalog} headingLevel="h2" />}>
          {searchParams.then((sp) => (
            <TransferMiniForm catalog={catalog} headingLevel="h2" showRoute initial={{ hub: one(sp.hub), destination: one(sp.to), date: one(sp.date), point: toPoint(one(sp.to_lat), one(sp.to_lng)) }} />
          ))}
        </Suspense>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {perks.map(({ Icon, text }) => (
            <li key={text} className="flex items-center gap-3 rounded-card border border-ink-100 bg-white p-4 shadow-card">
              <span className="grid size-11 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700"><Icon className="size-5" aria-hidden /></span>
              <span className="text-ink-900">{text}</span>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}
