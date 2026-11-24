import { Faq } from "@/components/trips/Faq";
import { buttonClasses } from "@/components/ui/Button";
import { PageIntro } from "@/components/ui/PageIntro";
import { Link } from "@/i18n/navigation";
import { getFaqs, htmlToText } from "@/lib/api/content";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("faqPage");
  return pageMetadata({ locale, path: "/faq", title: t("metaTitle"), description: t("subtitle") });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [t, categories] = await Promise.all([getTranslations("faqPage"), getFaqs(locale)]);
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: categories.flatMap((c) => c.items.map((i) => ({ "@type": "Question", name: i.question, acceptedAnswer: { "@type": "Answer", text: htmlToText(i.answer_html) } }))),
  };
  return (
    <div className="container-page pb-16">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <PageIntro crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")}>
        <nav aria-label={t("categoriesLabel")} className="no-scrollbar -mx-4 mt-5 overflow-x-auto px-4">
          <ul className="flex gap-2">
            {categories.map((c) => (
              <li key={c.id}>
                <a href={`#${c.id}`} className="inline-flex h-10 items-center rounded-pill bg-ink-100 px-4 text-sm font-semibold whitespace-nowrap text-ink-900 hover:bg-primary-100">
                  {c.category}
                </a>
              </li>
            ))}
          </ul>
        </nav>
      </PageIntro>
      <div className="mt-4 flex max-w-4xl flex-col gap-10">
        {categories.map((c) => (
          <section key={c.id} id={c.id} className="scroll-mt-32" aria-labelledby={`${c.id}-h`}>
            <h2 id={`${c.id}-h`} className="text-h3 mb-4 text-ink-900">{c.category}</h2>
            <Faq items={c.items} />
          </section>
        ))}
        <section className="rounded-card bg-sand-50 p-6 md:p-8" aria-labelledby="still-h">
          <h2 id="still-h" className="font-display text-2xl font-semibold text-ink-900">{t("stillTitle")}</h2>
          <p className="mt-1 text-ink-700">{t("stillText")}</p>
          <Link href="/contact" className={buttonClasses("cta", "md", "mt-4")}>{t("contactCta")}</Link>
        </section>
      </div>
    </div>
  );
}
