// Builds SVG paths for chart bars whose data end is rounded while the end on the baseline stays square.
// The radius shrinks for thin or short bars so the corners never overlap.

const HALF = 2;

function cornerRadius(radius: number, thickness: number, length: number): number {
  return Math.max(0, Math.min(radius, thickness / HALF, length));
}

export function columnBarPath(
  x: number,
  y: number,
  width: number,
  height: number,
  radius: number,
): string {
  const corner = cornerRadius(radius, width, height);
  const right = x + width;
  const bottom = y + height;

  return [
    `M ${x} ${bottom}`,
    `V ${y + corner}`,
    `A ${corner} ${corner} 0 0 1 ${x + corner} ${y}`,
    `H ${right - corner}`,
    `A ${corner} ${corner} 0 0 1 ${right} ${y + corner}`,
    `V ${bottom}`,
    "Z",
  ].join(" ");
}

export function horizontalBarPath(
  x: number,
  y: number,
  width: number,
  height: number,
  radius: number,
): string {
  const corner = cornerRadius(radius, height, width);
  const right = x + width;
  const bottom = y + height;

  return [
    `M ${x} ${y}`,
    `H ${right - corner}`,
    `A ${corner} ${corner} 0 0 1 ${right} ${y + corner}`,
    `V ${bottom - corner}`,
    `A ${corner} ${corner} 0 0 1 ${right - corner} ${bottom}`,
    `H ${x}`,
    "Z",
  ].join(" ");
}
