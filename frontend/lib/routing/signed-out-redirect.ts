// Optimistic proxy check that sends visitors without a session cookie to the sign in page.
// It is a routing convenience only, because the backend rejects every unauthenticated call.

import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { serverEnvironment } from "@/config/server-environment";
import { AppRoute, PUBLIC_ROUTES } from "@/constants/routes";

export function getSignedOutRedirect(request: NextRequest): NextResponse | null {
  if (PUBLIC_ROUTES.has(request.nextUrl.pathname)) {
    return null;
  }

  if (request.cookies.has(serverEnvironment.sessionCookieName)) {
    return null;
  }

  return NextResponse.redirect(new URL(AppRoute.SignIn, request.url));
}
