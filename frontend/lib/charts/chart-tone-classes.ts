// Maps each chart tone to the Tailwind fill and stroke classes of its colour token.
// The typed records make the build fail when a tone has no class.

import { ChartTone } from "@/enums/ui/chart-tone";

export const CHART_TONE_FILL_CLASSES: Record<ChartTone, string> = {
  [ChartTone.Primary]: "fill-primary",
  [ChartTone.Success]: "fill-success",
  [ChartTone.Warning]: "fill-warning",
  [ChartTone.Muted]: "fill-muted",
};

export const CHART_TONE_STROKE_CLASSES: Record<ChartTone, string> = {
  [ChartTone.Primary]: "stroke-primary",
  [ChartTone.Success]: "stroke-success",
  [ChartTone.Warning]: "stroke-warning",
  [ChartTone.Muted]: "stroke-muted",
};
