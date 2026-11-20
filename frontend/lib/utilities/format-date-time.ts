// Formats ISO 8601 timestamps from the API as a readable date and time.
// The value is shown in the browser's time zone with the shared display locale.

import { DISPLAY_LOCALE } from "@/constants/application";

const dateTimeFormatter = new Intl.DateTimeFormat(DISPLAY_LOCALE, {
  dateStyle: "medium",
  timeStyle: "short",
});

export function formatDateTime(isoTimestamp: string): string {
  return dateTimeFormatter.format(new Date(isoTimestamp));
}
