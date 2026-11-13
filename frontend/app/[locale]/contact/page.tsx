import { ContactForm } from "@/components/content/ContactForm";
import { PageIntro } from "@/components/ui/PageIntro";
import { getMeta } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { CONTACT_EMAIL, PHONE_DISPLAY, WHATSAPP_NUMBER } from "@/lib/site";
import { Clock, Mail, MapPin, MessageCircle, Phone, Timer } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("contact");
  return pageMetadata({ locale, path: "/contact", title: t("metaTitle"), description: t("subtitle") });
}

const ADDRESS = "Naama Bay, Sharm El Sheikh, Egypt";

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [t, meta] = await Promise.all([getTranslations("contact"), getMeta(locale)]);
  // Contact details come from GET /meta settings, with the site constants as fallback.
  const email = meta.settings.email || CONTACT_EMAIL;
  const phone = meta.settings.phone || PHONE_DISPLAY;
  const whatsappDisplay = meta.settings.whatsapp || `+${WHATSAPP_NUMBER}`;
  const whatsappDigits = whatsappDisplay.replace(/\D/g, "") || WHATSAPP_NUMBER;
  const rows = [
    { Icon: MessageCircle, label: t("whatsapp"), value: whatsappDisplay, href: `https://wa.me/${whatsappDigits}`, external: true },
    { Icon: Mail, label: t("emailUs"), value: email, href: `mailto:${email}` },
    { Icon: Phone, label: t("call"), value: phone, href: `tel:${phone.replace(/[^\d+]/g, "")}` },
    { Icon: MapPin, label: t("address"), value: ADDRESS },
  ];
  return (
    <div className="container-page pb-16">
      <PageIntro crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      <div className="mt-2 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-8" aria-labelledby="form-h">
          <h2 id="form-h" className="text-h4 mb-5 text-ink-900">{t("formTitle")}</h2>
          <ContactForm email={email} whatsapp={whatsappDisplay} />
        </section>
        <aside className="flex flex-col gap-6">
          <section className="rounded-card bg-sand-50 p-6" aria-labelledby="direct-h">
            <h2 id="direct-h" className="font-display text-xl font-semibold text-ink-900">{t("directTitle")}</h2>
            <ul className="mt-4 flex flex-col gap-4">
              {rows.map(({ Icon, label, value, href, external }) => (
                <li key={label} className="flex items-start gap-3">
                  <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white text-primary-700"><Icon className="size-5" aria-hidden /></span>
                  <span className="min-w-0">
                    <span className="block text-xs text-ink-500">{label}</span>
                    {href ? (
                      <a href={href} {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})} className="break-words font-semibold text-primary-700 hover:underline">
                        {value}
                      </a>
                    ) : (
                      <span className="font-semibold text-ink-900">{value}</span>
                    )}
                  </span>
                </li>
              ))}
            </ul>
          </section>
          <section className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
            <h2 className="flex items-center gap-2 font-semibold text-ink-900"><Clock className="size-5 text-primary-700" aria-hidden />{t("hoursTitle")}</h2>
            <p className="mt-1 text-sm text-ink-700">{t("hoursText")}</p>
            <h2 className="mt-4 flex items-center gap-2 font-semibold text-ink-900"><Timer className="size-5 text-primary-700" aria-hidden />{t("responseTitle")}</h2>
            <p className="mt-1 text-sm text-ink-700">{t("responseText")}</p>
          </section>
        </aside>
      </div>
      <section className="mt-12" aria-labelledby="find-h">
        <h2 id="find-h" className="text-h3 mb-4 text-ink-900">{t("findUs")}</h2>
        <div className="grid min-h-[260px] place-items-center rounded-card border border-ink-100 bg-[linear-gradient(90deg,var(--color-ink-100)_1px,transparent_1px),linear-gradient(var(--color-ink-100)_1px,transparent_1px)] bg-[size:48px_48px] p-6 text-center">
          <div className="flex flex-col items-center gap-3">
            <span className="grid size-12 place-items-center rounded-full bg-cta-600 text-white shadow-cta"><MapPin className="size-6" aria-hidden /></span>
            <p className="font-semibold text-ink-900">{ADDRESS}</p>
            <p className="text-sm text-ink-500">{t("mapPlaceholder")}</p>
            <a href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ADDRESS)}`} target="_blank" rel="noopener noreferrer" className="font-semibold text-primary-700 underline">
              {t("openMaps")}
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}
