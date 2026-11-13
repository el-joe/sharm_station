import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { useTranslations } from "next-intl";

export function PackageRouteBand({ stops }: { stops: { name: string; text: string }[] }) {
  const t = useTranslations("home.packages");
  return (
    <div className="mb-8 rounded-card border border-ink-100 bg-white p-6 shadow-card md:p-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-lg font-semibold text-ink-900 md:text-[22px]">{t("routeTitle")}</h3>
        <Badge tone="info" icon={BadgeIcons.transfers}>{t("transfersIncluded")}</Badge>
      </div>
      <ol className="mt-6 flex flex-col gap-5 md:flex-row md:items-start md:gap-0">
        {stops.map((s, i) => (
          <li key={s.name} className="flex flex-1 items-start gap-3 md:items-center">
            <span className="mt-1 size-5 shrink-0 rounded-full bg-primary-700 md:mt-0" aria-hidden />
            <div className="md:max-w-[220px]">
              <p className="font-semibold text-ink-900">{s.name}</p>
              <p className="text-sm text-ink-700">{s.text}</p>
            </div>
            {i < stops.length - 1 && <span className="mx-6 hidden h-0.5 flex-1 bg-ink-300 md:block" aria-hidden />}
          </li>
        ))}
      </ol>
    </div>
  );
}
