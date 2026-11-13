import { PartnerPriceList } from "@/components/partner/PartnerPriceList";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("partnerPanel.priceList");
  return pageMetadata({ locale, path: "/partner/price-list", title: t("metaTitle"), description: t("subtitle"), noindex: true });
}

export default function Page() {
  return <PartnerPriceList />;
}
