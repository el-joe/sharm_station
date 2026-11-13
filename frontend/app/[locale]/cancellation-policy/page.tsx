import { StaticDoc } from "@/components/content/StaticDoc";
import type { Locale } from "@/lib/api/types";
import { PAGES } from "@/lib/content/pages";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = (await rootLocale()) as Locale;
  const doc = PAGES["cancellation-policy"][locale];
  return pageMetadata({ locale, path: "/cancellation-policy", title: doc.title, description: doc.description });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const t = await getTranslations("footer");
  return <StaticDoc doc={PAGES["cancellation-policy"][locale]} slug="cancellation-policy" crumb={t("cancellation")} />;
}
