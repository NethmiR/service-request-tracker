// Frame shared by every chart, holding the labelled SVG image and its visually hidden data table.
// The SVG scales with its container and scrolls sideways below a minimum width so its labels stay readable.

import { useId, type ReactNode } from "react";
import { ChartDataTable } from "@/components/shared/charts/chart-data-table";
import type { ChartDataProps } from "@/components/shared/charts/chart-series";
import { CHART_VIEW_BOX_WIDTH } from "@/constants/charts";

interface ChartFigureProps<Datum> extends ChartDataProps<Datum> {
  title: string;
  tableCaption: string;
  viewBoxHeight: number;
  children: ReactNode;
}

export function ChartFigure<Datum>({
  title,
  tableCaption,
  viewBoxHeight,
  children,
  ...tableProps
}: ChartFigureProps<Datum>) {
  const titleId = useId();

  return (
    <figure className="flex flex-col">
      <div className="overflow-x-auto">
        <svg
          role="img"
          aria-labelledby={titleId}
          viewBox={`0 0 ${CHART_VIEW_BOX_WIDTH} ${viewBoxHeight}`}
          className="h-auto w-full min-w-lg"
        >
          <title id={titleId}>{title}</title>
          {children}
        </svg>
      </div>
      <ChartDataTable caption={tableCaption} {...tableProps} />
    </figure>
  );
}
