// Legend that names each chart series next to a square in the series colour.
// The label text stays in the normal text colour, so identity never depends on colour alone.

import { LEGEND_SWATCH_RADIUS, LEGEND_SWATCH_SIZE } from "@/constants/charts";
import { CHART_TONE_FILL_CLASSES } from "@/lib/charts/chart-tone-classes";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";
import type { ChartLegendItem } from "@/components/shared/charts/chart-series";

interface ChartLegendProps {
  items: readonly ChartLegendItem[];
  className?: string;
}

export function ChartLegend({ items, className }: ChartLegendProps) {
  return (
    <ul className={mergeClassNames("flex flex-wrap items-center gap-x-5 gap-y-2", className)}>
      {items.map((item) => (
        <li key={item.key} className="flex items-center gap-2 text-sm text-foreground">
          <svg
            aria-hidden="true"
            width={LEGEND_SWATCH_SIZE}
            height={LEGEND_SWATCH_SIZE}
            viewBox={`0 0 ${LEGEND_SWATCH_SIZE} ${LEGEND_SWATCH_SIZE}`}
            className="shrink-0"
          >
            <rect
              width={LEGEND_SWATCH_SIZE}
              height={LEGEND_SWATCH_SIZE}
              rx={LEGEND_SWATCH_RADIUS}
              className={CHART_TONE_FILL_CLASSES[item.tone]}
            />
          </svg>
          {item.label}
        </li>
      ))}
    </ul>
  );
}
