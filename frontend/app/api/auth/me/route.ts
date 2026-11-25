import type { NextRequest } from "next/server";
import { currentUser } from "@/lib/server/auth-handlers";

/** GET /api/auth/me -> { data: user } for the session cookie, 401 (cookies cleared) without a valid session. */
export function GET(request: NextRequest) {
  return currentUser(request);
}
