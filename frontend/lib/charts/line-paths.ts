// Builds the SVG paths of a line series, breaking the line wherever a value is missing.
// Missing months therefore show as gaps and never as zeros.

export interface ChartPoint {
  x: number;
  y: number;
}

const MINIMUM_SEGMENT_POINTS = 2;

export function linePaths(points: readonly (ChartPoint | null)[]): string[] {
  const segments: ChartPoint[][] = [];
  let currentSegment: ChartPoint[] = [];

  for (const point of points) {
    if (point === null) {
      segments.push(currentSegment);
      currentSegment = [];
    } else {
      currentSegment.push(point);
    }
  }

  segments.push(currentSegment);

  return segments
    .filter((segment) => segment.length >= MINIMUM_SEGMENT_POINTS)
    .map((segment) =>
      segment
        .map((point, index) => `${index === 0 ? "M" : "L"} ${point.x} ${point.y}`)
        .join(" "),
    );
}
