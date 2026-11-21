// Vertical bar chart with one or more series drawn side by side for each category.
// Each bar has a native tooltip and a hover area as tall as the plot, and the exact figures sit in a hidden table.

import { BottomCategoryLabels, HorizontalValueGrid } from "@/components/shared/charts/chart-axes";
import { ChartFigure } from "@/components/shared/charts/chart-figure";
import { seriesValues, type ChartProps } from "@/components/shared/charts/chart-series";
import {
  BAR_CORNER_RADIUS,
  BAR_SERIES_GAP,
  COLUMN_CHART_VIEW_BOX_HEIGHT,
  COLUMN_GROUP_GAP_RATIO,
} from "@/constants/charts";
import { columnBarPath } from "@/lib/charts/bar-path";
import { niceMaximum, scaleValue } from "@/lib/charts/chart-scale";
import { CHART_TONE_FILL_CLASSES } from "@/lib/charts/chart-tone-classes";
import { formatChartDatumTitle, formatChartValue } from "@/lib/charts/format-chart-value";
import { centerOffset, plotHeight, plotWidth, verticalPlotArea } from "@/lib/charts/plot-area";

export function ColumnChart<Datum>({
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
  const plot = verticalPlotArea(COLUMN_CHART_VIEW_BOX_HEIGHT);
  const height = plotHeight(plot);
  const maximum = niceMaximum(seriesValues(data, series));
  const bandWidth = data.length === 0 ? 0 : plotWidth(plot) / data.length;
  const groupWidth = bandWidth * (1 - COLUMN_GROUP_GAP_RATIO);
  const seriesGaps = BAR_SERIES_GAP * Math.max(series.length - 1, 0);
  const barWidth = series.length === 0 ? 0 : Math.max(groupWidth - seriesGaps, 0) / series.length;

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
      viewBoxHeight={COLUMN_CHART_VIEW_BOX_HEIGHT}
    >
      <HorizontalValueGrid plot={plot} maximum={maximum} axisLabel={valueAxisLabel} />
      {data.map((datum, index) => {
        const groupStart = plot.left + index * bandWidth + centerOffset(bandWidth, groupWidth);

        return (
          <g key={categoryKey(datum)}>
            {series.map((item, seriesIndex) => {
              const value = item.value(datum);
              const x = groupStart + seriesIndex * (barWidth + BAR_SERIES_GAP);
              const barHeight = value === null ? 0 : scaleValue(value, maximum, height);

              return (
                <g key={item.key}>
                  <title>
                    {formatChartDatumTitle(
                      categoryLabel(datum),
                      item.label,
                      formatChartValue(value, missingValueText),
                    )}
                  </title>
                  <rect
                    x={x}
                    y={plot.top}
                    width={barWidth}
                    height={height}
                    className="fill-transparent"
                  />
                  {barHeight > 0 && (
                    <path
                      d={columnBarPath(
                        x,
                        plot.bottom - barHeight,
                        barWidth,
                        barHeight,
                        BAR_CORNER_RADIUS,
                      )}
                      className={CHART_TONE_FILL_CLASSES[item.tone]}
                    />
                  )}
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
          center: plot.left + index * bandWidth + centerOffset(bandWidth, 0),
        }))}
      />
    </ChartFigure>
  );
}
