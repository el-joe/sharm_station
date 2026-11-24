import { StaticDoc } from "@/components/content/StaticDoc";
import { getPage } from "@/lib/api/content";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = (await rootLocale()) as Locale;
  const doc = await getPage("cancellation-policy", locale);
  if (!doc) return {};
  return pageMetadata({ locale, path: "/cancellation-policy", title: doc.seo.title, description: doc.seo.description });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [doc, t] = await Promise.all([getPage("cancellation-policy", locale), getTranslations("footer")]);
  if (!doc) notFound();
  return <StaticDoc doc={doc} crumb={t("cancellation")} />;
}
