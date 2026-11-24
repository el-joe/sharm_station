import { MapEmbed } from "@/components/map/MapEmbed";
import { hasCoords } from "@/components/map/types";
import { MapPin } from "lucide-react";
import { useTranslations } from "next-intl";

/** Meeting point on a Google Maps embed, with "Get directions" / "Open in Google Maps". */
export function MeetingPoint({ text, lat, lng, label, title }: { text: string; lat: number; lng: number; label: string; title?: string }) {
  const t = useTranslations("trip");
  const heading = title ?? t("meetingPoint");
  const body = (
    <>
      <h3 className="text-[22px] font-semibold text-ink-900">{heading}</h3>
      {label && (
        <p className="mt-1 flex items-center gap-2 font-medium text-ink-900">
          <MapPin className="size-4 shrink-0 text-cta-600" aria-hidden /> {label}
        </p>
      )}
      <p className="mt-1 text-ink-700">{text}</p>
    </>
  );
  if (!hasCoords(lat, lng)) return <div className="rounded-card border border-ink-100 bg-white p-6">{body}</div>;
  return (
    <MapEmbed lat={lat} lng={lng} name={label || heading} label={`${heading}: ${label || text}`} mapClassName="h-56 md:h-72">
      {body}
    </MapEmbed>
  );
}
