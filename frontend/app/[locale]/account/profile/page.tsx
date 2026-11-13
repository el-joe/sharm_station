import { AccountNav } from "@/components/account/AccountNav";
import { ProfileForm } from "@/components/account/ProfileForm";
import { PageIntro } from "@/components/ui/PageIntro";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("account.profile");
  return pageMetadata({ locale, path: "/account/profile", title: t("metaTitle"), description: t("subtitle"), noindex: true });
}

export default async function Page() {
  const t = await getTranslations("account");
  return (
    <>
      <PageIntro crumbs={[{ label: t("crumb"), href: "/account/bookings" }, { label: t("nav.profile") }]} title={t("profile.title")} subtitle={t("profile.subtitle")}>
        <AccountNav />
      </PageIntro>
      <ProfileForm />
    </>
  );
}
