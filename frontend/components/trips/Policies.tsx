import { Baby, ShieldCheck } from "lucide-react";
import { useTranslations } from "next-intl";

export function Policies({ cancellation, childrenPolicy }: { cancellation: string; childrenPolicy: string }) {
  const t = useTranslations("trip");
  const items = [
    { icon: <ShieldCheck className="size-6" aria-hidden />, title: t("cancellationPolicy"), text: cancellation },
    { icon: <Baby className="size-6" aria-hidden />, title: t("childrenPolicy"), text: childrenPolicy },
  ];
  return (
    <div className="grid gap-4 md:grid-cols-2">
      {items.map((p) => (
        <div key={p.title} className="rounded-card border border-ink-100 bg-white p-6">
          <h3 className="flex items-center gap-3 text-[22px] font-semibold text-ink-900">
            <span className="text-ink-900">{p.icon}</span>
            {p.title}
          </h3>
          <p className="mt-3 text-ink-700">{p.text}</p>
        </div>
      ))}
    </div>
  );
}
