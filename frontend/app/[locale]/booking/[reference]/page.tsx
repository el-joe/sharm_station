import { BookingConfirmation } from "@/components/booking/BookingConfirmation";
import type { Locale } from "@/lib/api/types";
import { bookingStatusForMetadata, bookingTitleKind } from "@/lib/server/booking-status";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

/**
 * Title follows the booking status (received / confirmed / cancelled) when the server can read it
 * (session cookie or ?token= booking link); otherwise "Your booking {reference}", and
 * BookingConfirmation updates document.title once the booking is loaded in the browser.
 */
export async function generateMetadata({ params, searchParams }: PageProps<"/[locale]/booking/[reference]">): Promise<Metadata> {
  const { locale, reference } = await params;
  const query = await searchParams;
  const t = await getTranslations({ locale, namespace: "confirmation" });
  const token = typeof query.token === "string" ? query.token : null;
  const kind = bookingTitleKind(await bookingStatusForMetadata(reference, locale as Locale, token));
  const title =
    kind === "confirmed"
      ? t("metaTitleConfirmed", { reference })
      : kind === "cancelled"
        ? t("metaTitleCancelled", { reference })
        : kind === "received"
          ? t("metaTitleReceived", { reference })
          : t("metaTitleNeutral", { reference });
  return { title, robots: { index: false, follow: false } };
}

export default function BookingPage({ params }: PageProps<"/[locale]/booking/[reference]">) {
  return (
    <div className="container-page py-8 lg:py-12">
      <Suspense fallback={<div className="h-[500px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
        <BookingConfirmation params={params} />
      </Suspense>
    </div>
  );
}
