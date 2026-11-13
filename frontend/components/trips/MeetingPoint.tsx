import { buttonClasses } from "@/components/ui/Button";
import { MapPin } from "lucide-react";
import { useTranslations } from "next-intl";

/** Static map illustration (no third-party map script) + "Open in Maps". */
export function MeetingPoint({ text, lat, lng, label, title }: { text: string; lat: number; lng: number; label: string; title?: string }) {
  const t = useTranslations("trip");
  return (
    <div className="overflow-hidden rounded-card border border-ink-100 bg-white">
      <div className="relative h-52 bg-sand-50" aria-hidden>
        <svg viewBox="0 0 800 210" preserveAspectRatio="none" className="absolute inset-0 h-full w-full">
          <path d="M0 120 C 200 60, 420 200, 800 20 L800 70 C 420 240, 200 110, 0 170 Z" fill="#cbcee0" opacity="0.7" />
          <rect x="40" y="20" width="150" height="70" rx="12" fill="#fff" opacity="0.8" />
          <rect x="300" y="110" width="160" height="80" rx="12" fill="#fff" opacity="0.8" />
          <rect x="560" y="60" width="150" height="80" rx="12" fill="#fff" opacity="0.8" />
        </svg>
        <span className="absolute start-1/2 top-[42%] flex -translate-x-1/2 items-center gap-2 rtl:translate-x-1/2">
          <span className="grid size-6 place-items-center rounded-full bg-cta-600 text-white">
            <MapPin className="size-3.5" />
          </span>
          <span className="rounded-pill bg-white px-3 py-1 text-xs font-medium text-ink-900 shadow-card">{label}</span>
        </span>
      </div>
      <div className="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 className="text-[22px] font-semibold text-ink-900">{title ?? t("meetingPoint")}</h3>
          <p className="mt-1 text-ink-700">{text}</p>
        </div>
        <a href={`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`} target="_blank" rel="noopener noreferrer" className={buttonClasses("outline", "md", "shrink-0")}>
          {t("openInMaps")}
        </a>
      </div>
    </div>
  );
}
