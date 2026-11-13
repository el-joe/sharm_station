import { CheckCircle2, XCircle } from "lucide-react";
import { useTranslations } from "next-intl";

export function IncludedList({ included, excluded }: { included: string[]; excluded: string[] }) {
  const t = useTranslations("trip");
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <div className="rounded-card border border-ink-100 bg-white p-6">
        <h3 className="text-[22px] font-semibold text-ink-900">{t("included")}</h3>
        <ul className="mt-4 flex flex-col gap-3">
          {included.map((i) => (
            <li key={i} className="flex gap-3 text-ink-700">
              <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
              <span>{i}</span>
            </li>
          ))}
        </ul>
      </div>
      <div className="rounded-card border border-brand-100 bg-sand-50 p-6">
        <h3 className="text-[22px] font-semibold text-ink-900">{t("notIncluded")}</h3>
        <ul className="mt-4 flex flex-col gap-3">
          {excluded.map((i) => (
            <li key={i} className="flex gap-3 text-ink-700">
              <XCircle className="mt-0.5 size-5 shrink-0 text-ink-500" aria-hidden />
              <span>{i}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
