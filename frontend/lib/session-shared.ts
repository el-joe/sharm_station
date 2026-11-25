/** Names shared by the browser and the /api/auth + /api/proxy route handlers (see lib/server/session.ts). */
export const SESSION_COOKIE = "ssv_session";
/** Non-httpOnly "1" while a session cookie exists: lets the client skip /api/auth/me for anonymous visitors. */
export const SESSION_HINT_COOKIE = "ssv_signed_in";
export const CSRF_HEADER = "x-ssv-csrf";
/** Legacy localStorage key (before F6): exchanged once for the cookie, then removed. */
export const LEGACY_TOKEN_KEY = "ssv_token";
