import { AccountNav } from "@/components/account/AccountNav";
import { AccountGreeting } from "@/components/account/AccountGreeting";
import { MyBookings } from "@/components/account/MyBookings";
import { PageIntro } from "@/components/ui/PageIntro";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("account.bookings");
  return pageMetadata({ locale, path: "/account/bookings", title: t("metaTitle"), description: t("emptyUpcoming"), noindex: true });
}

export default async function Page() {
  const t = await getTranslations("account");
  return (
    <>
      <PageIntro crumbs={[{ label: t("crumb"), href: "/account/bookings" }, { label: t("nav.bookings") }]} title={t("bookings.title")} subtitle={<AccountGreeting />}>
        <AccountNav />
      </PageIntro>
      <MyBookings />
    </>
  );
}
