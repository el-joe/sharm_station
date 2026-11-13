import { PartnerStatements } from "@/components/partner/PartnerStatements";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("partnerPanel.statements");
  return pageMetadata({ locale, path: "/partner/statements", title: t("metaTitle"), description: t("subtitle"), noindex: true });
}

export default function Page() {
  return <PartnerStatements />;
}
