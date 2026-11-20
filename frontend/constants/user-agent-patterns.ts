// Ordered browser and platform patterns used to describe a stored user agent.
// Order matters because user agents contain each other's tokens, so the most specific comes first.

import type { UserAgentPattern } from "@/types/sessions/user-agent-pattern";

export const BROWSER_PATTERNS: readonly UserAgentPattern[] = [
  { pattern: /Edg\//, label: "Edge" },
  { pattern: /OPR\//, label: "Opera" },
  { pattern: /Firefox\/|FxiOS\//, label: "Firefox" },
  { pattern: /Chrome\/|CriOS\//, label: "Chrome" },
  { pattern: /Version\/.*Safari\//, label: "Safari" },
];

export const PLATFORM_PATTERNS: readonly UserAgentPattern[] = [
  { pattern: /iPhone|iPad/, label: "iOS" },
  { pattern: /Android/, label: "Android" },
  { pattern: /Windows NT/, label: "Windows" },
  { pattern: /CrOS/, label: "ChromeOS" },
  { pattern: /Mac OS X/, label: "macOS" },
  { pattern: /Linux/, label: "Linux" },
];
