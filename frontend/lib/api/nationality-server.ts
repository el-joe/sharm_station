import { cookies } from "next/headers";
import { NATIONALITY_COOKIE, normalizeNationality } from "../nationality-shared";

/**
 * The visitor's nationality from the `ssv_nationality` cookie (request-time data).
 * Call it only inside a <Suspense> boundary and pass the value to the cached getters in
 * ./server.ts, where it becomes part of the cache key: prices are cached per nationality,
 * never shared across nationalities. Missing -> undefined (the API prices with the default group).
 */
export async function getNationality(): Promise<string | undefined> {
  const store = await cookies();
  return normalizeNationality(store.get(NATIONALITY_COOKIE)?.value) ?? undefined;
}
