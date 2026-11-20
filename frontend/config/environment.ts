// Reads and validates the public environment values the frontend needs.
// Fails fast with a clear error when a required value is missing.

import { DeveloperMessage } from "@/constants/developer-messages";

const configuredApiUrl = process.env.NEXT_PUBLIC_API_URL;

if (!configuredApiUrl) {
  throw new Error(DeveloperMessage.MissingApiUrl);
}

const apiBaseUrl = configuredApiUrl.replace(/\/+$/, "");

export const environment = Object.freeze({
  apiBaseUrl,
  apiOrigin: new URL(apiBaseUrl).origin,
});
