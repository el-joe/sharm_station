import { ResetPasswordForm } from "@/components/account/ResetPasswordForm";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("passwordReset");
  return pageMetadata({ locale, path: "/reset-password", title: t("resetTitle"), description: t("resetText"), noindex: true });
}

/** /reset-password?token=&email=: target of the welcome and forgot-password e-mails (API §5). */
export default async function Page() {
  const t = await getTranslations("passwordReset");
  return (
    <div className="bg-sand-50">
      <div className="container-page flex justify-center py-12 lg:py-20">
        <div className="w-full max-w-md rounded-panel border border-ink-100 bg-white p-6 shadow-card md:p-10">
          <h1 className="text-h2 text-ink-900">{t("resetTitle")}</h1>
          <p className="mb-6 mt-2 text-ink-700">{t("resetText")}</p>
          <ResetPasswordForm />
        </div>
      </div>
    </div>
  );
}
