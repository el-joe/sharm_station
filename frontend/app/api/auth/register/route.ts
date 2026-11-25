import type { NextRequest } from "next/server";
import { issueSession } from "@/lib/server/auth-handlers";

/** POST /api/auth/register {name, email, password, password_confirmation, locale} -> 201 { data: user } + session cookie. */
export function POST(request: NextRequest) {
  return issueSession(request, "auth/register");
}
