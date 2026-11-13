import { Badge, BadgeIcons } from "@/components/ui/Badge";
import type { PackageStage } from "@/lib/api/types";
import { Hotel } from "lucide-react";
import { useTranslations } from "next-intl";

/** Package stages (SRS 4.2) with the "Transfers included" badge. */
export function PackageStages({ stages }: { stages: PackageStage[] }) {
  const t = useTranslations("package");
  return (
    <div className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-lg font-semibold text-ink-900">{t("route")}</h3>
        <Badge tone="info" icon={BadgeIcons.transfers}>{t("transfersIncluded")}</Badge>
      </div>
      <ol className="mt-5 flex flex-col gap-5">
        {stages.map((s, i) => (
          <li key={s.destination} className="grid grid-cols-[32px_1fr] gap-3">
            <span className="grid size-8 place-items-center rounded-full bg-primary-700 text-sm font-semibold text-white" aria-hidden>{i + 1}</span>
            <div>
              <p className="font-semibold text-ink-900">
                {s.destination} <span className="font-normal text-ink-700">· {t("daysNights", { days: s.days, nights: s.nights })}</span>
              </p>
              <p className="text-sm text-ink-700">{s.description}</p>
              {s.accommodation && (
                <p className="mt-1 inline-flex items-center gap-1.5 text-sm text-ink-700"><Hotel className="size-4" aria-hidden />{s.accommodation}</p>
              )}
            </div>
          </li>
        ))}
      </ol>
      <p className="mt-5 rounded-image bg-primary-100 px-4 py-3 text-sm text-ink-900">{t("transfersNote")}</p>
    </div>
  );
}
