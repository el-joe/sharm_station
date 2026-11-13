"use client";

import { AppImage } from "@/components/ui/AppImage";
import type { MediaImage } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";

/** Mosaic gallery + thumbnail strip + accessible lightbox (<dialog>). */
export function TripGallery({ images }: { images: MediaImage[] }) {
  const t = useTranslations("gallery");
  const [index, setIndex] = useState<number | null>(null);
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (index != null && !d.open) d.showModal();
    if (index == null && d.open) d.close();
  }, [index]);

  const go = (delta: number) => setIndex((i) => (i == null ? 0 : (i + delta + images.length) % images.length));
  const tiles = images.slice(0, 5);
  const extra = images.length - tiles.length;

  return (
    <>
      <div className="grid grid-cols-2 gap-2 md:grid-cols-[1.6fr_1fr] md:grid-rows-2">
        {tiles.slice(0, 3).map((img, i) => (
          <button
            key={img.url + i}
            type="button"
            onClick={() => setIndex(i)}
            className={cn("relative overflow-hidden rounded-image bg-ink-100", i === 0 ? "col-span-2 aspect-[16/10] md:col-span-1 md:row-span-2 md:aspect-auto" : "aspect-[4/3] md:aspect-auto md:h-[146px]")}
            aria-label={t("open", { n: i + 1, total: images.length })}
          >
            <AppImage src={img.url} alt={img.alt} fill sizes={i === 0 ? "(min-width: 1024px) 480px, 100vw" : "(min-width: 1024px) 300px, 50vw"} className="object-cover transition-transform duration-500 hover:scale-105" />
            {i === 0 && <span className="text-eyebrow absolute bottom-3 start-3 rounded-pill bg-brand-navy-900 px-2.5 py-1 text-white">{t("featured")}</span>}
          </button>
        ))}
      </div>
      {tiles.length > 3 && (
        <div className="mt-2 grid grid-cols-2 gap-2">
          {tiles.slice(3, 5).map((img, j) => {
            const i = j + 3;
            const last = i === tiles.length - 1 && extra > 0;
            return (
              <button key={img.url + i} type="button" onClick={() => setIndex(i)} className="relative aspect-[392/140] overflow-hidden rounded-image bg-ink-100" aria-label={last ? t("viewAll", { total: images.length }) : t("open", { n: i + 1, total: images.length })}>
                <AppImage src={img.url} alt={img.alt} fill sizes="(min-width: 1024px) 392px, 50vw" className="object-cover" />
                {last && (
                  <span className="absolute inset-0 grid place-items-center bg-brand-navy-900/50 text-white">
                    <span className="text-center">
                      <span className="block font-display text-3xl">+{extra}</span>
                      <span className="text-eyebrow">{t("viewAllShort")}</span>
                    </span>
                  </span>
                )}
              </button>
            );
          })}
        </div>
      )}
      <ul className="no-scrollbar mt-3 flex gap-3 overflow-x-auto pb-1">
        {images.map((img, i) => (
          <li key={img.url + i} className="shrink-0">
            <button type="button" onClick={() => setIndex(i)} className="relative block h-16 w-24 overflow-hidden rounded-control bg-ink-100" aria-label={t("open", { n: i + 1, total: images.length })}>
              <AppImage src={img.url} alt="" fill sizes="96px" className="object-cover" />
            </button>
          </li>
        ))}
      </ul>

      <dialog
        ref={ref}
        onClose={() => setIndex(null)}
        onKeyDown={(e) => {
          if (e.key === "ArrowRight") go(1);
          if (e.key === "ArrowLeft") go(-1);
        }}
        aria-label={t("viewer")}
        className="m-auto h-dvh max-h-none w-screen max-w-none bg-brand-navy-900/95 p-0 backdrop:bg-black/70 [&:not([open])]:hidden"
      >
        {index != null && (
          <div className="relative flex h-full flex-col">
            <div className="flex items-center justify-between p-4 text-white">
              <p aria-live="polite">{t("counter", { n: index + 1, total: images.length })}</p>
              <button type="button" onClick={() => setIndex(null)} className="grid size-11 place-items-center rounded-full hover:bg-white/10" aria-label={t("close")}>
                <X className="size-6" aria-hidden />
              </button>
            </div>
            <div className="relative flex-1">
              <AppImage src={images[index].url} alt={images[index].alt} fill sizes="100vw" className="object-contain" />
            </div>
            <div className="flex justify-center gap-4 p-4">
              <button type="button" onClick={() => go(-1)} className="grid size-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label={t("previous")}>
                <ChevronLeft className="size-6 rtl:rotate-180" aria-hidden />
              </button>
              <button type="button" onClick={() => go(1)} className="grid size-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label={t("next")}>
                <ChevronRight className="size-6 rtl:rotate-180" aria-hidden />
              </button>
            </div>
          </div>
        )}
      </dialog>
    </>
  );
}
