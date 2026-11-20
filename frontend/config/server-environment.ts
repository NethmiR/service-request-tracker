// Reads and validates the server only environment values used by the Next.js proxy.
// Only proxy.ts and the modules it imports may import this file, so the values never reach the browser.

import { DeveloperMessage } from "@/constants/developer-messages";

const sessionCookieName = process.env.SESSION_COOKIE_NAME;

if (!sessionCookieName) {
  throw new Error(DeveloperMessage.MissingSessionCookieName);
}

export const serverEnvironment = Object.freeze({
  sessionCookieName,
});
