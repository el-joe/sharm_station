import { SITE_URL } from "@/lib/site";
import type { MetadataRoute } from "next";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Transactional and partner pages are never indexed (SRS 15.3).
        disallow: ["/*/checkout", "/*/booking/", "/*/partner", "/*/account", "/*/sign-in", "/*/sign-up"],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}
