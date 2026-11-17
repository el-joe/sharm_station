import { PartnerNewBooking } from "@/components/partner/new/PartnerNewBooking";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("partnerPanel.newBooking");
  return pageMetadata({ locale, path: "/partner/new", title: t("metaTitle"), description: t("subtitle"), noindex: true });
}

export default function Page() {
  return <PartnerNewBooking />;
}
