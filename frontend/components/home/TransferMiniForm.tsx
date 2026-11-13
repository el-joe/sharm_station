"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input, Select } from "@/components/ui/Field";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, TransferCatalog, TransferQuoteRequest } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { useToday } from "@/lib/hooks";
import { whatsappHref } from "@/lib/site";
import { addDays, cn, parseISODate, toISODate } from "@/lib/utils";
import { useMutation } from "@tanstack/react-query";
import { Car, CheckCircle2, MessageCircle } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useId, useState, type FormEvent } from "react";

/** Figma TransferMiniForm (62:14811): From, To, Date, Vehicle + Get price. */
export function TransferMiniForm({
  catalog,
  initial,
  className,
  headingLevel = "h3",
}: {
  catalog: TransferCatalog;
  initial?: Partial<Pick<TransferQuoteRequest, "hub" | "destination" | "date" | "vehicle">>;
  className?: string;
  headingLevel?: "h2" | "h3";
}) {
  const t = useTranslations("transfers");
  const id = useId();
  const today = useToday();
  const locale = useLocale() as Locale;
  const { hubs, vehicles, live } = catalog;
  const [hub, setHub] = useState(hubs.some((h) => h.code === initial?.hub) ? initial!.hub! : (hubs[0]?.code ?? ""));
  const [destination, setDestination] = useState(initial?.destination ?? "");
  const [dateState, setDate] = useState<string | null>(initial?.date || null);
  const [vehicle, setVehicle] = useState<string>(vehicles.some((v) => v.code === initial?.vehicle) ? initial!.vehicle! : (vehicles[0]?.code ?? "sedan"));
  const [error, setError] = useState<string | null>(null);
  const date = dateState ?? (today ? toISODate(addDays(parseISODate(today), 7)) : "");
  const Heading = headingLevel;

  const quote = useMutation({
    mutationFn: (body: TransferQuoteRequest) => api.quoteTransfer(body, { locale }, catalog),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!destination.trim()) {
      setError(t("toRequired"));
      return;
    }
    setError(null);
    quote.mutate({ hub, destination: destination.trim(), date, vehicle });
  };

  const hubName = hubs.find((h) => h.code === hub)?.name ?? hub;
  const places = hubs.find((h) => h.code === hub)?.places ?? [];
  const vehicleLabel = (code: string) => {
    const v = vehicles.find((x) => x.code === code);
    if (!live || !v) return ["sedan", "minivan", "van"].includes(code) ? t(`vehicles.${code as "sedan"}`) : code;
    return v.capacity ? t("vehicleCapacity", { name: v.name, count: v.capacity }) : v.name;
  };
  const unknownPlace = quote.error instanceof ApiError && quote.error.status === 422;

  return (
    <div className={cn("rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-8", className)}>
      <Heading className="font-display text-[22px] font-semibold leading-[30px] text-ink-900 md:text-[28px] md:leading-9">{t("formTitle")}</Heading>
      <p className="mt-2 text-ink-700">{t("formSubtitle")}</p>
      <form onSubmit={submit} noValidate className="mt-5 flex flex-col gap-4">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label={t("from")} htmlFor={`${id}-from`}>
            <Select id={`${id}-from`} value={hub} onChange={(e) => setHub(e.target.value)}>
              {hubs.map((h) => (
                <option key={h.code} value={h.code}>{h.name}</option>
              ))}
            </Select>
          </Field>
          <Field label={t("to")} htmlFor={`${id}-to`} error={error ?? undefined}>
            <Input id={`${id}-to`} list={places.length ? `${id}-places` : undefined} value={destination} onChange={(e) => setDestination(e.target.value)} placeholder={t("toPlaceholder")} invalid={Boolean(error)} aria-describedby={error ? `${id}-to-error` : undefined} autoComplete="off" />
            {places.length > 0 && (
              <datalist id={`${id}-places`}>
                {places.map((p) => (
                  <option key={p.name} value={p.name} />
                ))}
              </datalist>
            )}
          </Field>
          <Field label={t("date")} htmlFor={`${id}-date`}>
            <Input id={`${id}-date`} type="date" value={date} min={today ? toISODate(addDays(parseISODate(today), 1)) : undefined} onChange={(e) => setDate(e.target.value)} />
          </Field>
          <Field label={t("vehicle")} htmlFor={`${id}-vehicle`}>
            <Select id={`${id}-vehicle`} value={vehicle} onChange={(e) => setVehicle(e.target.value)}>
              {vehicles.map((v) => (
                <option key={v.code} value={v.code}>{vehicleLabel(v.code)}</option>
              ))}
            </Select>
          </Field>
        </div>
        <Button type="submit" size="lg" className="w-full" loading={quote.isPending}>
          {t("getPrice")}
        </Button>
        <div aria-live="polite">
          {quote.data ? (
            <div className="flex flex-col gap-3 rounded-image bg-success-100 p-4 sm:flex-row sm:items-center sm:justify-between">
              <p className="flex items-start gap-2 text-ink-900">
                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
                <span>
                  {t("quoteResult", { from: hubName, to: destination, km: quote.data.distance_km, minutes: quote.data.duration_minutes })}{" "}
                  <Price amount={quote.data.price} className="font-bold" />
                </span>
              </p>
              <a
                href={whatsappHref(t("whatsappRequest", { from: hubName, to: destination, date, vehicle: vehicleLabel(vehicle) }))}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 font-semibold text-primary-700 hover:underline"
              >
                <MessageCircle className="size-4" aria-hidden /> {t("request")}
              </a>
            </div>
          ) : quote.isError ? (
            <p className="text-sm text-error" role="alert">
              {unknownPlace ? t("unknownPlace") : t("error")}{" "}
              <a href={whatsappHref(t("whatsappRequest", { from: hubName, to: destination, date, vehicle: vehicleLabel(vehicle) }))} target="_blank" rel="noopener noreferrer" className="font-semibold underline">
                WhatsApp
              </a>
            </p>
          ) : (
            <p className="flex items-center gap-2 text-sm text-ink-700">
              <Car className="size-5 shrink-0" aria-hidden /> {t("hint")}
            </p>
          )}
        </div>
      </form>
    </div>
  );
}
