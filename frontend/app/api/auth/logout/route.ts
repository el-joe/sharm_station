import type { NextRequest } from "next/server";
import { endSession } from "@/lib/server/auth-handlers";

/** POST /api/auth/logout -> 204, token revoked and session cookies cleared. */
export function POST(request: NextRequest) {
  return endSession(request);
}
