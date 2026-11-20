// Turns a stored user agent into a short device label such as Chrome on Windows.
// Uses the ordered pattern tables and falls back to a generic label when nothing matches.

import { formatDeviceLabel, SessionMessage } from "@/constants/session-messages";
import { BROWSER_PATTERNS, PLATFORM_PATTERNS } from "@/constants/user-agent-patterns";
import type { UserAgentPattern } from "@/types/sessions/user-agent-pattern";

function findLabel(userAgent: string, patterns: readonly UserAgentPattern[]): string | null {
  return patterns.find(({ pattern }) => pattern.test(userAgent))?.label ?? null;
}

export function describeUserAgent(userAgent: string | null): string {
  if (userAgent === null) {
    return SessionMessage.UnknownDevice;
  }

  return formatDeviceLabel(
    findLabel(userAgent, BROWSER_PATTERNS),
    findLabel(userAgent, PLATFORM_PATTERNS),
  );
}
