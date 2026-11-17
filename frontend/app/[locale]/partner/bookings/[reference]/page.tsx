import { PartnerBookingDetail } from "@/components/partner/PartnerBookingDetail";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

export async function generateMetadata({ params }: PageProps<"/[locale]/partner/bookings/[reference]">): Promise<Metadata> {
  const { reference } = await params;
  const t = await getTranslations("partnerPanel.detail");
  return { title: t("metaTitle", { reference }), robots: { index: false, follow: false } };
}

export default function Page({ params }: PageProps<"/[locale]/partner/bookings/[reference]">) {
  return (
    <Suspense fallback={<div className="h-[600px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
      <PartnerBookingDetail params={params} />
    </Suspense>
  );
}
