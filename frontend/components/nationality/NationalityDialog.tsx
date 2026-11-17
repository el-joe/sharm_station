"use client";

import { Button } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { useNationality } from "@/lib/nationality";
import { normalizeNationality, regionFromLanguage } from "@/lib/nationality-shared";
import { cn } from "@/lib/utils";
import { Globe, Info, Loader2, Lock, MapPin, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState, useSyncExternalStore } from "react";
import { NationalityCombobox } from "./NationalityCombobox";

type Source = "detected" | "browser" | "current" | null;

const noopSubscribe = () => () => {};

/**
 * Nationality picker (Figma 250:31886 gate, 250:32030 header picker; mobile 252:24282 bottom sheet).
 *
 * mode "required": first-visit gate. No close button, Esc and backdrop do nothing, the page behind is
 * inert (native modal <dialog>), focus stays inside and the page cannot scroll. Continue stays
 * disabled until a country is chosen.
 * mode "change": the same picker opened from the header chip, with close, Cancel and Apply.
 */
export function NationalityDialog({ open, mode, onDone }: { open: boolean; mode: "required" | "change"; onDone: () => void }) {
  const t = useTranslations("nationality");
  const { code, data, loading, setNationality } = useNationality();
  const ref = useRef<HTMLDialogElement>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);
  const titleId = useId();
  const descId = useId();
  const required = mode === "required";
  // The gate sits in its own Suspense boundary, which can hydrate after the countries query has
  // resolved: render the server markup ("Loading countries…") until hydration is done, or React
  // reports a hydration mismatch (#418) and re-renders the whole boundary.
  const hydrated = useSyncExternalStore(noopSubscribe, () => true, () => false);

  const [choice, setChoice] = useState<{ code: string | null; source: Source } | null>(null);
  const [saving, setSaving] = useState(false);

  // Preselect: current choice -> API `detected` -> navigator.language region -> none.
  // Read the browser language only after hydration: the server cannot know it, and a hydration-time
  // difference (e.g. Continue enabled on the client, disabled in the server HTML) is never patched by
  // React, which left the Continue button stuck as disabled.
  const browser = hydrated && typeof navigator !== "undefined" ? regionFromLanguage(navigator.languages?.find((l) => regionFromLanguage(l)) ?? navigator.language) : null;
  const known = (c: string | null | undefined) => (c && data?.countries.some((x) => x.code === c) ? c : null);
  const fallback: { code: string | null; source: Source } = known(code)
    ? { code: code!, source: "current" }
    : known(normalizeNationality(data?.detected))
      ? { code: normalizeNationality(data?.detected), source: "detected" }
      : known(browser)
        ? { code: browser, source: "browser" }
        : { code: null, source: null };
  const current = choice ?? fallback;

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      dialog.showModal();
      headingRef.current?.focus();
    }
    if (!open && dialog.open) dialog.close();
    if (!open) return;
    // Lock page scroll while the picker is open.
    const root = document.documentElement;
    const previous = root.style.overflow;
    root.style.overflow = "hidden";
    return () => {
      root.style.overflow = previous;
    };
  }, [open]);

  const close = () => {
    setChoice(null);
    onDone();
  };

  const submit = async () => {
    if (!current.code) return;
    setSaving(true);
    try {
      await setNationality(current.code);
    } finally {
      setSaving(false);
      setChoice(null);
      onDone();
    }
  };

  const selectedCountry = data?.countries.find((c) => c.code === current.code);

  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      aria-describedby={descId}
      aria-modal="true"
      onCancel={(e) => {
        e.preventDefault();
        if (!required) close();
      }}
      onClose={() => {
        // A second Esc can force-close a modal dialog in some browsers: reopen the mandatory gate.
        if (required && open && ref.current && !ref.current.open) ref.current.showModal();
      }}
      onKeyDown={(e) => {
        if (e.key === "Escape" && required) e.preventDefault();
      }}
      onClick={(e) => {
        if (!required && e.target === ref.current) close();
      }}
      className={cn(
        "m-0 w-full max-w-none overflow-visible bg-white p-0 text-ink-900 backdrop:bg-brand-navy-900/60 [&:not([open])]:hidden",
        // Mobile: bottom sheet. Desktop: centred card.
        "fixed inset-x-0 bottom-0 top-auto max-h-[92dvh] rounded-t-panel open:animate-sheet-up",
        "md:inset-0 md:m-auto md:h-fit md:max-h-[calc(100dvh-48px)] md:w-[560px] md:rounded-panel md:open:animate-fade-in",
      )}
    >
      <div className="flex justify-center pt-3 md:hidden" aria-hidden>
        <span className="h-1 w-10 rounded-pill bg-ink-300" />
      </div>
      <div className="flex flex-col gap-5 px-5 pb-[max(20px,env(safe-area-inset-bottom))] pt-4 md:px-10 md:pb-8 md:pt-10">
        <div className="flex items-start justify-between gap-4">
          <span className="grid size-11 place-items-center rounded-full bg-primary-100 text-primary-700" aria-hidden>
            <Globe className="size-6" />
          </span>
          {!required && (
            <button type="button" onClick={close} className="-me-2 -mt-2 grid size-11 place-items-center rounded-full hover:bg-ink-100" aria-label={t("close")}>
              <X className="size-6" aria-hidden />
            </button>
          )}
        </div>
        <div>
          <h2 ref={headingRef} id={titleId} tabIndex={-1} className="font-display text-[28px] font-semibold leading-9 text-ink-900 outline-none">
            {required ? t("title") : t("changeTitle")}
          </h2>
          <p id={descId} className="mt-1 text-ink-900">
            {t("subtitle")}
          </p>
          <p className="mt-2 text-sm text-ink-500">{required ? t("text") : t("changeText")}</p>
        </div>

        {loading || !data || !hydrated ? (
          <div className="flex h-12 items-center gap-2 text-ink-700" role="status">
            <Loader2 className="size-5 animate-spin" aria-hidden /> {t("loading")}
          </div>
        ) : (
          <div className="flex flex-col gap-1.5">
            <NationalityCombobox
              id={`${titleId}-country`}
              countries={data.countries}
              value={current.code}
              onChange={(c) => setChoice({ code: c, source: null })}
              suggestions={[normalizeNationality(data.detected), browser, code]}
              label={t("label")}
              required
              describedBy={`${titleId}-hint`}
            />
            <p id={`${titleId}-hint`} className="flex items-center gap-1.5 text-xs" aria-live="polite">
              {current.source === "detected" && (
                <span className="flex items-center gap-1 text-success">
                  <MapPin className="size-3.5" aria-hidden /> {t("detected")}
                </span>
              )}
              {current.source === "browser" && (
                <span className="flex items-center gap-1 text-ink-500">
                  <Globe className="size-3.5" aria-hidden /> {t("fromBrowser")}
                </span>
              )}
              {selectedCountry && current.source !== "detected" && current.source !== "browser" && (
                <span className="text-ink-500">{t("groupHint", { group: selectedCountry.group.name })}</span>
              )}
            </p>
          </div>
        )}

        {!required && (
          <p className="flex items-start gap-2 rounded-image bg-sand-50 p-3 text-xs text-ink-700">
            <Info className="mt-0.5 size-4 shrink-0 text-primary-700" aria-hidden /> {t("changeNote")}
          </p>
        )}

        {required ? (
          <Button size="lg" className="w-full" disabled={!current.code} loading={saving} onClick={submit}>
            {t("continue")}
          </Button>
        ) : (
          <div className="flex items-center justify-end gap-2">
            <Button variant="ghost" onClick={close}>
              {t("cancel")}
            </Button>
            <Button variant="primary" disabled={!current.code} loading={saving} onClick={submit}>
              {t("apply")}
            </Button>
          </div>
        )}

        <p className="flex items-start gap-2 text-xs text-ink-500">
          <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden />
          <span>
            {t.rich("privacy", {
              link: (chunks) => (
                <Link href="/privacy" className="underline" target={required ? "_blank" : undefined} onClick={required ? undefined : close}>
                  {chunks}
                </Link>
              ),
            })}
          </span>
        </p>
      </div>
    </dialog>
  );
}
