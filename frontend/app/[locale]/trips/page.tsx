import { ListingPage } from "@/components/trips/ListingPage";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("listing.trips");
  return pageMetadata({ locale, path: "/trips", title: t("metaTitle"), description: t("subtitle") });
}

export default async function Page({ searchParams }: PageProps<"/[locale]/trips">) {
  const locale = (await rootLocale()) as Locale;
  return <ListingPage kind="trips" locale={locale} searchParams={searchParams} />;
}
