// Line chart with one or more series and a point at each value, centred on its category.
// A missing value breaks the line, so gaps never read as zeros, and each point has a native tooltip.

import { BottomCategoryLabels, HorizontalValueGrid } from "@/components/shared/charts/chart-axes";
import { ChartFigure } from "@/components/shared/charts/chart-figure";
import { seriesValues, type ChartProps } from "@/components/shared/charts/chart-series";
import {
  LINE_CHART_VIEW_BOX_HEIGHT,
  LINE_STROKE_WIDTH,
  POINT_HIT_RADIUS,
  POINT_RADIUS,
  POINT_RING_WIDTH,
} from "@/constants/charts";
import { niceMaximum, scaleValue } from "@/lib/charts/chart-scale";
import { CHART_TONE_FILL_CLASSES, CHART_TONE_STROKE_CLASSES } from "@/lib/charts/chart-tone-classes";
import { formatChartDatumTitle, formatChartValue } from "@/lib/charts/format-chart-value";
import { linePaths, type ChartPoint } from "@/lib/charts/line-paths";
import { centerOffset, plotHeight, plotWidth, verticalPlotArea } from "@/lib/charts/plot-area";

export function LineChart<Datum>({
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
  const plot = verticalPlotArea(LINE_CHART_VIEW_BOX_HEIGHT);
  const height = plotHeight(plot);
  const maximum = niceMaximum(seriesValues(data, series));
  const bandWidth = data.length === 0 ? 0 : plotWidth(plot) / data.length;
  const centers = data.map((_, index) => plot.left + index * bandWidth + centerOffset(bandWidth, 0));

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
      viewBoxHeight={LINE_CHART_VIEW_BOX_HEIGHT}
    >
      <HorizontalValueGrid plot={plot} maximum={maximum} axisLabel={valueAxisLabel} />
      {series.map((item) => {
        const points: (ChartPoint | null)[] = data.map((datum, index) => {
          const value = item.value(datum);

          return value === null
            ? null
            : { x: centers[index] ?? plot.left, y: plot.bottom - scaleValue(value, maximum, height) };
        });

        return (
          <g key={item.key}>
            {linePaths(points).map((path) => (
              <path
                key={path}
                d={path}
                strokeWidth={LINE_STROKE_WIDTH}
                strokeLinejoin="round"
                strokeLinecap="round"
                className={`fill-none ${CHART_TONE_STROKE_CLASSES[item.tone]}`}
              />
            ))}
            {data.map((datum, index) => {
              const point = points[index];

              if (point === null || point === undefined) {
                return null;
              }

              return (
                <g key={categoryKey(datum)}>
                  <title>
                    {formatChartDatumTitle(
                      categoryLabel(datum),
                      item.label,
                      formatChartValue(item.value(datum), missingValueText),
                    )}
                  </title>
                  <circle
                    cx={point.x}
                    cy={point.y}
                    r={POINT_HIT_RADIUS}
                    className="fill-transparent"
                  />
                  <circle
                    cx={point.x}
                    cy={point.y}
                    r={POINT_RADIUS}
                    strokeWidth={POINT_RING_WIDTH}
                    className={`stroke-surface ${CHART_TONE_FILL_CLASSES[item.tone]}`}
                  />
                </g>
              );
            })}
          </g>
        );
      })}
      <BottomCategoryLabels
        plot={plot}
        labels={data.map((datum, index) => ({
          key: categoryKey(datum),
          label: axisCategoryLabel(datum),
          center: centers[index] ?? plot.left,
        }))}
      />
    </ChartFigure>
  );
}
