// Shortens an axis label that would not fit its space, ending it with an ellipsis.
// Callers keep the full label for the tooltips and the hidden data table.

const ELLIPSIS = "\u2026";

export function shortenChartLabel(label: string, maximumCharacters: number): string {
  const characters = Array.from(label);

  if (characters.length <= maximumCharacters) {
    return label;
  }

  return `${characters.slice(0, maximumCharacters - ELLIPSIS.length).join("").trimEnd()}${ELLIPSIS}`;
}
