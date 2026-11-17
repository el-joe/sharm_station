/**
 * Nationality pricing (SRS 3.9, CR-10): shared by server and client code.
 * The visitor's nationality (ISO-3166 alpha-2) lives in the `ssv_nationality` cookie and is
 * sent as `?nationality=XX` on every priced API request.
 */
export const NATIONALITY_COOKIE = "ssv_nationality";

/** One year, in seconds. */
export const NATIONALITY_MAX_AGE = 60 * 60 * 24 * 365;

/** "it" / "IT" -> "IT"; anything that is not two letters -> null. */
export function normalizeNationality(value: string | null | undefined): string | null {
  const code = value?.trim().toUpperCase();
  return code && /^[A-Z]{2}$/.test(code) ? code : null;
}

/** Regional-indicator flag emoji from an ISO alpha-2 code ("IT" -> 🇮🇹). */
export function flagEmoji(code: string | null | undefined): string {
  const c = normalizeNationality(code);
  if (!c) return "";
  return String.fromCodePoint(...[...c].map((ch) => 0x1f1e6 + ch.charCodeAt(0) - 65));
}

/** Region of a BCP-47 language tag ("it-IT" -> "IT", "en" -> null). */
export function regionFromLanguage(tag: string | null | undefined): string | null {
  if (!tag) return null;
  const parts = tag.split(/[-_]/);
  for (let i = parts.length - 1; i > 0; i--) {
    const region = normalizeNationality(parts[i]);
    if (region) return region;
  }
  return null;
}
