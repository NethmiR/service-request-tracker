// Horizontal bar chart with one row per category and the series stacked from left to right.
// The total sits at the end of each bar, and the view box grows with the number of rows.

import { VerticalValueGrid } from "@/components/shared/charts/chart-axes";
import { ChartFigure } from "@/components/shared/charts/chart-figure";
import type { ChartProps } from "@/components/shared/charts/chart-series";
import {
  BAR_CORNER_RADIUS,
  BAR_SERIES_GAP,
  CHART_LABEL_FONT_SIZE,
  CHART_LABEL_OFFSET,
  CHART_PADDING_BOTTOM,
  CHART_PADDING_TOP,
  CHART_VIEW_BOX_WIDTH,
  STACKED_BAR_PADDING_LEFT,
  STACKED_BAR_PADDING_RIGHT,
  STACKED_BAR_ROW_HEIGHT,
  STACKED_BAR_THICKNESS_RATIO,
} from "@/constants/charts";
import { horizontalBarPath } from "@/lib/charts/bar-path";
import { niceMaximum, scaleValue } from "@/lib/charts/chart-scale";
import { CHART_TONE_FILL_CLASSES } from "@/lib/charts/chart-tone-classes";
import {
  formatChartDatumTitle,
  formatChartNumber,
  formatChartValue,
} from "@/lib/charts/format-chart-value";
import { centerOffset, plotWidth, type PlotArea } from "@/lib/charts/plot-area";

export function StackedBarChart<Datum>({
  data,
  categoryKey,
  categoryLabel,
  axisCategoryLabel = categoryLabel,
  categoryHeader,
  series,
  missingValueText,
  title,
  valueAxisLabel,
  tableCaption,
}: ChartProps<Datum>) {
  const viewBoxHeight =
    CHART_PADDING_TOP + data.length * STACKED_BAR_ROW_HEIGHT + CHART_PADDING_BOTTOM;
  const plot: PlotArea = {
    left: STACKED_BAR_PADDING_LEFT,
    right: CHART_VIEW_BOX_WIDTH - STACKED_BAR_PADDING_RIGHT,
    top: CHART_PADDING_TOP,
    bottom: viewBoxHeight - CHART_PADDING_BOTTOM,
  };
  const width = plotWidth(plot);
  const thickness = STACKED_BAR_ROW_HEIGHT * STACKED_BAR_THICKNESS_RATIO;
  const totals = data.map((datum) =>
    series.reduce((total, item) => total + (item.value(datum) ?? 0), 0),
  );
  const maximum = niceMaximum(totals);

  return (
    <ChartFigure
      data={data}
      categoryKey={categoryKey}
      categoryLabel={categoryLabel}
      categoryHeader={categoryHeader}
      series={series}
      missingValueText={missingValueText}
      title={title}
      tableCaption={tableCaption}
      viewBoxHeight={viewBoxHeight}
    >
      <VerticalValueGrid plot={plot} maximum={maximum} axisLabel={valueAxisLabel} />
      {data.map((datum, index) => {
        const rowTop = plot.top + index * STACKED_BAR_ROW_HEIGHT;
        const rowCenter = rowTop + centerOffset(STACKED_BAR_ROW_HEIGHT, 0);
        const barTop = rowTop + centerOffset(STACKED_BAR_ROW_HEIGHT, thickness);
        const total = totals[index] ?? 0;
        const segmentWidths = series.map((item) =>
          scaleValue(item.value(datum) ?? 0, maximum, width),
        );
        const lastVisibleIndex = segmentWidths.findLastIndex((segmentWidth) => segmentWidth > 0);
        const segmentStarts = segmentWidths.map(
          (_, segmentIndex) =>
            plot.left +
            segmentWidths.slice(0, segmentIndex).reduce((sum, segmentWidth) => sum + segmentWidth, 0),
        );

        return (
          <g key={categoryKey(datum)}>
            <text
              aria-hidden="true"
              x={plot.left - CHART_LABEL_OFFSET}
              y={rowCenter}
              textAnchor="end"
              dominantBaseline="middle"
              fontSize={CHART_LABEL_FONT_SIZE}
              className="fill-foreground"
            >
              {axisCategoryLabel(datum)}
            </text>
            {series.map((item, segmentIndex) => {
              const segmentWidth = segmentWidths[segmentIndex] ?? 0;

              if (segmentWidth <= 0) {
                return null;
              }

              const x = segmentStarts[segmentIndex] ?? plot.left;
              const isLastVisible = segmentIndex === lastVisibleIndex;
              const drawnWidth = isLastVisible
                ? segmentWidth
                : Math.max(segmentWidth - BAR_SERIES_GAP, 0);
              const toneClassName = CHART_TONE_FILL_CLASSES[item.tone];

              return (
                <g key={item.key}>
                  <title>
                    {formatChartDatumTitle(
                      categoryLabel(datum),
                      item.label,
                      formatChartValue(item.value(datum), missingValueText),
                    )}
                  </title>
                  {isLastVisible ? (
                    <path
                      d={horizontalBarPath(x, barTop, drawnWidth, thickness, BAR_CORNER_RADIUS)}
                      className={toneClassName}
                    />
                  ) : (
                    <rect
                      x={x}
                      y={barTop}
                      width={drawnWidth}
                      height={thickness}
                      className={toneClassName}
                    />
                  )}
                </g>
              );
            })}
            <text
              aria-hidden="true"
              x={plot.left + scaleValue(total, maximum, width) + CHART_LABEL_OFFSET}
              y={rowCenter}
              dominantBaseline="middle"
              fontSize={CHART_LABEL_FONT_SIZE}
              className="fill-foreground font-medium tabular-nums"
            >
              {formatChartNumber(total)}
            </text>
          </g>
        );
      })}
    </ChartFigure>
  );
}
