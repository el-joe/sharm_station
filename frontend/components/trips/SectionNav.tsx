import { useTranslations } from "next-intl";

/** In-page anchor navigation (Overview, Gallery, …, Book). */
export function SectionNav({ sections }: { sections: { id: string; key: string }[] }) {
  const t = useTranslations("trip.nav");
  return (
    <nav aria-label={t("label")} className="sticky top-16 z-20 border-b border-ink-100 bg-white lg:top-[116px]">
      <ul className="container-page no-scrollbar flex gap-1 overflow-x-auto md:justify-center md:gap-4">
        {sections.map((s) => (
          <li key={s.id} className="shrink-0">
            <a href={`#${s.id}`} className="inline-flex h-14 items-center border-b-2 border-transparent px-3 text-ink-700 hover:border-brand-500 hover:text-ink-900">
              {t(s.key)}
            </a>
          </li>
        ))}
      </ul>
    </nav>
  );
}
