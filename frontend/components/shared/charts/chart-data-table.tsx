// Visually hidden table with the exact figures of a chart, one row per category and one column per series.
// Screen reader users read the numbers here, and a missing value shows the text its caller passes in.

import { formatChartValue } from "@/lib/charts/format-chart-value";
import type { ChartDataProps } from "@/components/shared/charts/chart-series";

interface ChartDataTableProps<Datum> extends ChartDataProps<Datum> {
  caption: string;
}

export function ChartDataTable<Datum>({
  data,
  categoryKey,
  categoryLabel,
  categoryHeader,
  series,
  missingValueText,
  caption,
}: ChartDataTableProps<Datum>) {
  return (
    <table className="sr-only">
      <caption>{caption}</caption>
      <thead>
        <tr>
          <th scope="col">{categoryHeader}</th>
          {series.map((item) => (
            <th key={item.key} scope="col">
              {item.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {data.map((datum) => (
          <tr key={categoryKey(datum)}>
            <th scope="row">{categoryLabel(datum)}</th>
            {series.map((item) => (
              <td key={item.key}>{formatChartValue(item.value(datum), missingValueText)}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
