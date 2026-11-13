import { Check, Luggage } from "lucide-react";
import { useTranslations } from "next-intl";

export function WhatToBring({ items }: { items: string[] }) {
  const t = useTranslations("trip");
  if (!items.length) return null;
  return (
    <section aria-labelledby="bring-title" className="rounded-panel bg-brand-100 p-6">
      <h2 id="bring-title" className="flex items-center gap-2 text-[22px] font-semibold text-ink-900">
        <Luggage className="size-5" aria-hidden /> {t("whatToBring")}
      </h2>
      <ul className="mt-4 grid gap-2 text-sm text-ink-700 md:grid-cols-2">
        {items.map((i) => (
          <li key={i} className="flex items-center gap-2">
            <Check className="size-4 shrink-0 text-ink-900" aria-hidden /> {i}
          </li>
        ))}
      </ul>
    </section>
  );
}
