import { Link } from "@/i18n/navigation";
import { BedDouble, CalendarDays, Car, Luggage, Sun, Waves } from "lucide-react";
import { useTranslations } from "next-intl";

const ITEMS = [
  { key: "dayTrips", href: "/trips?type=single_day", Icon: Sun },
  { key: "multiDay", href: "/trips?type=multi_day", Icon: CalendarDays },
  { key: "nile", href: "/nile", Icon: Waves },
  { key: "packages", href: "/packages", Icon: Luggage },
  { key: "accommodation", href: "/accommodation", Icon: BedDouble },
  { key: "transfers", href: "/transfers", Icon: Car },
] as const;

export function CategoryTiles() {
  const t = useTranslations("home.categories");
  return (
    <section className="section-y" aria-labelledby="categories-title">
      <div className="container-page">
        <h2 id="categories-title" className="text-h2 text-ink-900">{t("title")}</h2>
        <p className="mt-2 text-ink-700 lg:text-lg">{t("subtitle")}</p>
        <ul className="no-scrollbar -mx-4 mt-8 flex gap-3 overflow-x-auto px-4 md:mx-0 md:mt-12 md:grid md:grid-cols-3 md:gap-6 md:overflow-visible md:px-0 lg:grid-cols-6">
          {ITEMS.map(({ key, href, Icon }) => (
            <li key={key} className="shrink-0">
              <Link
                href={href}
                className="flex h-11 items-center gap-2 rounded-pill border border-ink-300 bg-white px-4 font-semibold text-ink-900 hover:border-primary-500 md:h-[150px] md:flex-col md:justify-center md:gap-4 md:rounded-card md:border-ink-100 md:shadow-card md:hover:shadow-raised"
              >
                <span className="md:grid md:size-16 md:place-items-center md:rounded-full md:bg-primary-100">
                  <Icon className="size-5 text-primary-700 md:size-6" aria-hidden />
                </span>
                {t(key)}
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
