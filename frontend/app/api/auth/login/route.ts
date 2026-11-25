import type { NextRequest } from "next/server";
import { issueSession } from "@/lib/server/auth-handlers";

/** POST /api/auth/login {email, password} -> { data: user } + httpOnly session cookie (see lib/server/session.ts). */
export function POST(request: NextRequest) {
  return issueSession(request, "auth/login");
}
