// Formats chart values for axis labels, tooltips and the hidden data tables with the shared display locale.
// A missing value is shown with the text its caller passes in.

import { DISPLAY_LOCALE } from "@/constants/application";
import { CHART_VALUE_FRACTION_DIGITS } from "@/constants/charts";

const chartValueFormatter = new Intl.NumberFormat(DISPLAY_LOCALE, {
  maximumFractionDigits: CHART_VALUE_FRACTION_DIGITS,
});

export function formatChartNumber(value: number): string {
  return chartValueFormatter.format(value);
}

export function formatChartValue(value: number | null, missingValueText: string): string {
  return value === null ? missingValueText : formatChartNumber(value);
}

export function formatChartDatumTitle(
  categoryLabel: string,
  seriesLabel: string,
  formattedValue: string,
): string {
  return `${categoryLabel}, ${seriesLabel}: ${formattedValue}`;
}
