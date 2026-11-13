import { AccountBookingDetail } from "@/components/account/AccountBookingDetail";
import { AccountNav } from "@/components/account/AccountNav";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

export async function generateMetadata({ params }: PageProps<"/[locale]/account/bookings/[reference]">): Promise<Metadata> {
  const { reference } = await params;
  const t = await getTranslations("account.detail");
  return { title: t("metaTitle", { reference }), robots: { index: false, follow: false } };
}

export default function Page({ params }: PageProps<"/[locale]/account/bookings/[reference]">) {
  return (
    <div className="pt-6 lg:pt-10">
      <Suspense fallback={<div className="mt-6 h-[480px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
        <AccountNav />
        <AccountBookingDetail params={params} />
      </Suspense>
    </div>
  );
}
