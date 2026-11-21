// Geometry of the plot area inside a chart view box, shared by every chart.
// Also centres a mark inside its band so no chart repeats the arithmetic.

import {
  CHART_PADDING_BOTTOM,
  CHART_PADDING_LEFT,
  CHART_PADDING_RIGHT,
  CHART_PADDING_TOP,
  CHART_VIEW_BOX_WIDTH,
} from "@/constants/charts";

const HALF = 2;

export interface PlotArea {
  left: number;
  right: number;
  top: number;
  bottom: number;
}

export function verticalPlotArea(viewBoxHeight: number): PlotArea {
  return {
    left: CHART_PADDING_LEFT,
    right: CHART_VIEW_BOX_WIDTH - CHART_PADDING_RIGHT,
    top: CHART_PADDING_TOP,
    bottom: viewBoxHeight - CHART_PADDING_BOTTOM,
  };
}

export function plotWidth(plot: PlotArea): number {
  return plot.right - plot.left;
}

export function plotHeight(plot: PlotArea): number {
  return plot.bottom - plot.top;
}

export function centerOffset(outerLength: number, innerLength: number): number {
  return (outerLength - innerLength) / HALF;
}
