// Builds the nonce based Content Security Policy sent with every page.
// Development relaxes a few directives for React debugging, injected styles and hot reload.

import { environment } from "@/config/environment";
import { isDevelopmentRuntime } from "@/config/runtime-environment";

export const CONTENT_SECURITY_POLICY_HEADER = "Content-Security-Policy";

export const NONCE_HEADER = "x-nonce";

enum CspSource {
  Self = "'self'",
  None = "'none'",
  StrictDynamic = "'strict-dynamic'",
  UnsafeEval = "'unsafe-eval'",
  UnsafeInline = "'unsafe-inline'",
  Blob = "blob:",
  Data = "data:",
  WebSocket = "ws:",
}

const UPGRADE_INSECURE_REQUESTS = "upgrade-insecure-requests";

type CspDirective = readonly [name: string, sources: readonly string[]];

export function buildContentSecurityPolicy(nonce: string): string {
  const nonceSource = `'nonce-${nonce}'`;

  const directives: CspDirective[] = [
    ["default-src", [CspSource.Self]],
    [
      "script-src",
      isDevelopmentRuntime
        ? [CspSource.Self, nonceSource, CspSource.StrictDynamic, CspSource.UnsafeEval]
        : [CspSource.Self, nonceSource, CspSource.StrictDynamic],
    ],
    [
      "style-src",
      isDevelopmentRuntime ? [CspSource.Self, CspSource.UnsafeInline] : [CspSource.Self, nonceSource],
    ],
    ["style-src-attr", [CspSource.UnsafeInline]],
    ["img-src", [CspSource.Self, CspSource.Blob, CspSource.Data]],
    ["font-src", [CspSource.Self]],
    [
      "connect-src",
      isDevelopmentRuntime
        ? [CspSource.Self, environment.apiOrigin, CspSource.WebSocket]
        : [CspSource.Self, environment.apiOrigin],
    ],
    ["object-src", [CspSource.None]],
    ["base-uri", [CspSource.Self]],
    ["form-action", [CspSource.Self]],
    ["frame-ancestors", [CspSource.None]],
  ];

  const policy = directives.map(([name, sources]) => `${name} ${sources.join(" ")}`);

  if (!isDevelopmentRuntime) {
    policy.push(UPGRADE_INSECURE_REQUESTS);
  }

  return policy.join("; ");
}
