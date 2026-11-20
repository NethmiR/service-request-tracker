// Next.js proxy that runs before every page request.
// Generates a fresh CSP nonce and forwards it to Next.js and the browser through headers.

import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import {
  CONTENT_SECURITY_POLICY_HEADER,
  NONCE_HEADER,
  buildContentSecurityPolicy,
} from "@/lib/security/content-security-policy";

export function proxy(request: NextRequest) {
  const nonce = btoa(crypto.randomUUID());
  const contentSecurityPolicy = buildContentSecurityPolicy(nonce);

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set(NONCE_HEADER, nonce);
  requestHeaders.set(CONTENT_SECURITY_POLICY_HEADER, contentSecurityPolicy);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set(CONTENT_SECURITY_POLICY_HEADER, contentSecurityPolicy);

  return response;
}

export const config = {
  matcher: [
    {
      source: "/((?!_next/static|_next/image|favicon.ico).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
