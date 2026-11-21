// Grid lines, value labels and category labels shared by the charts.
// The value axis runs vertically for column and line charts and horizontally for stacked bar charts.

import type { Key } from "react";
import {
  CHART_AXIS_TICK_COUNT,
  CHART_AXIS_TITLE_OFFSET,
  CHART_LABEL_FONT_SIZE,
  CHART_LABEL_OFFSET,
  GRID_LINE_STROKE_WIDTH,
} from "@/constants/charts";
import { axisTicks, scaleValue } from "@/lib/charts/chart-scale";
import { formatChartNumber } from "@/lib/charts/format-chart-value";
import { plotHeight, plotWidth, type PlotArea } from "@/lib/charts/plot-area";

interface ValueGridProps {
  plot: PlotArea;
  maximum: number;
  axisLabel: string;
}

function gridLineClassName(tick: number): string {
  return tick === 0 ? "stroke-border" : "stroke-border-subtle";
}

function AxisTitle({ plot, label }: { plot: PlotArea; label: string }) {
  return (
    <text
      x={plot.left}
      y={plot.top - CHART_AXIS_TITLE_OFFSET}
      fontSize={CHART_LABEL_FONT_SIZE}
      className="fill-muted font-medium"
    >
      {label}
    </text>
  );
}

export function HorizontalValueGrid({ plot, maximum, axisLabel }: ValueGridProps) {
  const height = plotHeight(plot);

  return (
    <g aria-hidden="true">
      <AxisTitle plot={plot} label={axisLabel} />
      {axisTicks(maximum, CHART_AXIS_TICK_COUNT).map((tick) => {
        const y = plot.bottom - scaleValue(tick, maximum, height);

        return (
          <g key={tick}>
            <line
              x1={plot.left}
              x2={plot.right}
              y1={y}
              y2={y}
              strokeWidth={GRID_LINE_STROKE_WIDTH}
              className={gridLineClassName(tick)}
            />
            <text
              x={plot.left - CHART_LABEL_OFFSET}
              y={y}
              textAnchor="end"
              dominantBaseline="middle"
              fontSize={CHART_LABEL_FONT_SIZE}
              className="fill-muted tabular-nums"
            >
              {formatChartNumber(tick)}
            </text>
          </g>
        );
      })}
    </g>
  );
}

export function VerticalValueGrid({ plot, maximum, axisLabel }: ValueGridProps) {
  const width = plotWidth(plot);

  return (
    <g aria-hidden="true">
      <AxisTitle plot={plot} label={axisLabel} />
      {axisTicks(maximum, CHART_AXIS_TICK_COUNT).map((tick) => {
        const x = plot.left + scaleValue(tick, maximum, width);

        return (
          <g key={tick}>
            <line
              x1={x}
              x2={x}
              y1={plot.top}
              y2={plot.bottom}
              strokeWidth={GRID_LINE_STROKE_WIDTH}
              className={gridLineClassName(tick)}
            />
            <text
              x={x}
              y={plot.bottom + CHART_LABEL_OFFSET}
              textAnchor="middle"
              dominantBaseline="hanging"
              fontSize={CHART_LABEL_FONT_SIZE}
              className="fill-muted tabular-nums"
            >
              {formatChartNumber(tick)}
            </text>
          </g>
        );
      })}
    </g>
  );
}

export interface CategoryLabel {
  key: Key;
  label: string;
  center: number;
}

interface BottomCategoryLabelsProps {
  plot: PlotArea;
  labels: readonly CategoryLabel[];
}

export function BottomCategoryLabels({ plot, labels }: BottomCategoryLabelsProps) {
  return (
    <g aria-hidden="true">
      {labels.map((item) => (
        <text
          key={item.key}
          x={item.center}
          y={plot.bottom + CHART_LABEL_OFFSET}
          textAnchor="middle"
          dominantBaseline="hanging"
          fontSize={CHART_LABEL_FONT_SIZE}
          className="fill-muted"
        >
          {item.label}
        </text>
      ))}
    </g>
  );
}
