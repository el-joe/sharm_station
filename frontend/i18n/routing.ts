import { defineRouting } from "next-intl/routing";

export const locales = ["en", "it"] as const;
export type AppLocale = (typeof locales)[number];

/** Locales written right-to-left. Add "ar" here when Arabic is enabled. */
export const rtlLocales: readonly string[] = ["ar", "he", "fa"];

export const routing = defineRouting({
  locales,
  defaultLocale: "en",
  localePrefix: "always",
});

export function getDirection(locale: string): "ltr" | "rtl" {
  return rtlLocales.includes(locale) ? "rtl" : "ltr";
}
