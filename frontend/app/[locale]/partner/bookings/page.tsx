import { PartnerBookings } from "@/components/partner/PartnerBookings";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("partnerPanel.bookings");
  return pageMetadata({ locale, path: "/partner/bookings", title: t("metaTitle"), description: t("subtitle"), noindex: true });
}

export default function Page() {
  return <PartnerBookings />;
}
