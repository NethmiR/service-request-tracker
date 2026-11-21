// Shared types of the chart components, describing a series, the category accessors and the common chart props.
// Charts are generic over the datum type, so any feature can chart its own records without conversion.

import type { ChartTone } from "@/enums/ui/chart-tone";

export interface ChartLegendItem {
  key: string;
  label: string;
  tone: ChartTone;
}

export interface ChartSeries<Datum> extends ChartLegendItem {
  value: (datum: Datum) => number | null;
}

export type ChartCategory<Datum> = (datum: Datum) => string;

export interface ChartDataProps<Datum> {
  data: readonly Datum[];
  categoryKey: ChartCategory<Datum>;
  categoryLabel: ChartCategory<Datum>;
  categoryHeader: string;
  series: readonly ChartSeries<Datum>[];
  missingValueText: string;
}

export interface ChartProps<Datum> extends ChartDataProps<Datum> {
  axisCategoryLabel?: ChartCategory<Datum>;
  title: string;
  valueAxisLabel: string;
  tableCaption: string;
}

export function seriesValues<Datum>(
  data: readonly Datum[],
  series: readonly ChartSeries<Datum>[],
): number[] {
  return data.flatMap((datum) =>
    series.flatMap((item) => {
      const value = item.value(datum);

      return value === null ? [] : [value];
    }),
  );
}
