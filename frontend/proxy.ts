import createMiddleware from "next-intl/middleware";
import { routing } from "./i18n/routing";

// Next.js 16 renamed Middleware to Proxy. next-intl negotiates the locale
// (cookie, Accept-Language) and redirects "/" and unprefixed paths to /en or /it.
export default createMiddleware(routing);

export const config = {
  // Skip API routes, Next internals, metadata routes and static files.
  matcher: ["/((?!api|_next|_vercel|sitemap.xml|robots.txt|.*\\..*).*)"],
};
