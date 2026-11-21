// Formats a YYYY-MM reporting month key from the overview as a readable month, in a long and a short form.
// Dates are built and formatted in UTC, so the month never shifts in browsers behind UTC.

import { DISPLAY_LOCALE } from "@/constants/application";

const MONTH_KEY_PATTERN = /^(\d{4})-(\d{2})$/;

const MONTH_INDEX_OFFSET = 1;

const FIRST_DAY_OF_MONTH = 1;

const UTC_TIME_ZONE = "UTC";

const monthFormatter = new Intl.DateTimeFormat(DISPLAY_LOCALE, {
  month: "short",
  year: "numeric",
  timeZone: UTC_TIME_ZONE,
});

const shortMonthFormatter = new Intl.DateTimeFormat(DISPLAY_LOCALE, {
  month: "short",
  timeZone: UTC_TIME_ZONE,
});

function monthKeyToDate(month: string): Date | null {
  const match = MONTH_KEY_PATTERN.exec(month);

  if (match === null || match[1] === undefined || match[2] === undefined) {
    return null;
  }

  return new Date(
    Date.UTC(Number(match[1]), Number(match[2]) - MONTH_INDEX_OFFSET, FIRST_DAY_OF_MONTH),
  );
}

export function formatReportingMonth(month: string): string {
  const date = monthKeyToDate(month);

  return date === null ? month : monthFormatter.format(date);
}

export function formatReportingMonthShort(month: string): string {
  const date = monthKeyToDate(month);

  return date === null ? month : shortMonthFormatter.format(date);
}
