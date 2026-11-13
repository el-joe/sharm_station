/** Main navigation (Figma header: Trips, Nile Trips, Packages, Accommodation, Transfers, Destinations). */
export const NAV_ITEMS = [
  { key: "trips", href: "/trips" },
  { key: "nile", href: "/nile" },
  { key: "packages", href: "/packages" },
  { key: "accommodation", href: "/accommodation" },
  { key: "transfers", href: "/transfers" },
  { key: "destinations", href: "/destinations" },
] as const;

/** Extra links shown in the mobile drawer only. */
export const DRAWER_EXTRA = [{ key: "partner", href: "/become-a-partner" }] as const;
