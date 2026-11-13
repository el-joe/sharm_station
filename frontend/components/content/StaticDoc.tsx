import { buttonClasses } from "@/components/ui/Button";
import { PageIntro } from "@/components/ui/PageIntro";
import { Link } from "@/i18n/navigation";
import { LEGAL_LINKS, type Doc, type StaticSlug } from "@/lib/content/pages";
import { parseISODate } from "@/lib/utils";
import { getFormatter, getTranslations } from "next-intl/server";
import type { ReactNode } from "react";

/** Long-form page: intro, sticky "On this page" index, sections, questions box, related legal links. */
export async function StaticDoc({ doc, slug, crumb, aside, after }: { doc: Doc; slug: StaticSlug; crumb: string; aside?: ReactNode; after?: ReactNode }) {
  const [t, tf, format] = await Promise.all([getTranslations("legal"), getTranslations("footer"), getFormatter()]);
  const legal = LEGAL_LINKS.some((l) => l.slug === slug);
  return (
    <div className="container-page pb-16">
      <PageIntro crumbs={[{ label: crumb }]} title={doc.title} subtitle={doc.intro}>
        {legal && <p className="mt-3 text-sm text-ink-500">{t("lastUpdated", { date: format.dateTime(parseISODate(doc.updated), { day: "numeric", month: "long", year: "numeric" }) })}</p>}
      </PageIntro>
      <div className="mt-4 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside className="lg:sticky lg:top-32 lg:self-start">
          <nav aria-label={t("onThisPage")} className="rounded-card bg-sand-50 p-5">
            <h2 className="text-eyebrow text-ink-500">{t("onThisPage")}</h2>
            <ol className="mt-3 flex flex-col gap-1 text-sm">
              {doc.sections.map((s) => (
                <li key={s.id}>
                  <a href={`#${s.id}`} className="block rounded-control py-1.5 text-ink-700 hover:text-primary-700 hover:underline">
                    {s.heading}
                  </a>
                </li>
              ))}
            </ol>
          </nav>
          {aside}
        </aside>
        <article className="max-w-3xl">
          {doc.sections.map((s) => (
            <section key={s.id} id={s.id} className="scroll-mt-32 border-b border-ink-100 py-6 first:pt-0 last:border-0" aria-labelledby={`${s.id}-h`}>
              <h2 id={`${s.id}-h`} className="text-h3 text-ink-900">{s.heading}</h2>
              {s.paragraphs.map((p, i) => (
                <p key={i} className="mt-3 text-ink-700 lg:text-[17px] lg:leading-7">{p}</p>
              ))}
              {s.list && (
                <ul className="mt-3 flex list-disc flex-col gap-2 ps-6 text-ink-700 marker:text-brand-500">
                  {s.list.map((li) => (
                    <li key={li}>{li}</li>
                  ))}
                </ul>
              )}
            </section>
          ))}
          {after}
          <div className="mt-8 rounded-card border border-ink-100 bg-white p-6 shadow-card">
            <h2 className="font-display text-xl font-semibold text-ink-900">{t("questionsTitle")}</h2>
            <p className="mt-1 text-ink-700">{t("questionsText")}</p>
            <Link href="/contact" className={buttonClasses("cta", "md", "mt-4")}>{t("contactCta")}</Link>
          </div>
          {legal && (
            <nav aria-label={t("related")} className="mt-8">
              <h2 className="text-eyebrow text-ink-500">{t("related")}</h2>
              <ul className="mt-3 flex flex-wrap gap-2">
                {LEGAL_LINKS.filter((l) => l.slug !== slug).map((l) => (
                  <li key={l.slug}>
                    <Link href={`/${l.slug}`} className="inline-flex min-h-11 items-center rounded-pill bg-ink-100 px-4 text-sm font-semibold text-ink-900 hover:bg-primary-100">
                      {tf(l.key)}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          )}
        </article>
      </div>
    </div>
  );
}
