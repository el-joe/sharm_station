import type { NextRequest } from "next/server";
import { migrateSession } from "@/lib/server/auth-handlers";

/** POST /api/auth/migrate {token} -> exchanges a legacy localStorage token for the session cookie (once). */
export function POST(request: NextRequest) {
  return migrateSession(request);
}
