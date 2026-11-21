// Pure scale helpers for the shared charts, choosing a readable axis maximum, its ticks and the length of a value.
// Integer data always gets whole number ticks, so request counts never show fractional steps.

import { CHART_AXIS_TICK_COUNT } from "@/constants/charts";

const NICE_STEP_FACTORS = [1, 2, 5, 10] as const;

const DECIMAL_BASE = 10;

const MINIMUM_INTEGER_STEP = 1;

const EMPTY_AXIS_MAXIMUM = 1;

const TICK_VALUE_PRECISION = 12;

function niceStep(rawStep: number): number {
  const magnitude = Math.pow(DECIMAL_BASE, Math.floor(Math.log10(rawStep)));
  const normalized = rawStep / magnitude;
  const factor = NICE_STEP_FACTORS.find((candidate) => candidate >= normalized) ?? DECIMAL_BASE;

  return factor * magnitude;
}

export function niceMaximum(
  values: readonly number[],
  tickCount: number = CHART_AXIS_TICK_COUNT,
): number {
  const largest = Math.max(0, ...values);

  if (largest <= 0) {
    return EMPTY_AXIS_MAXIMUM;
  }

  const step = niceStep(largest / tickCount);
  const usesIntegers = values.every((value) => Number.isInteger(value));

  return (usesIntegers ? Math.max(step, MINIMUM_INTEGER_STEP) : step) * tickCount;
}

export function axisTicks(maximum: number, tickCount: number): number[] {
  return Array.from({ length: tickCount + 1 }, (_, index) =>
    Number(((maximum * index) / tickCount).toPrecision(TICK_VALUE_PRECISION)),
  );
}

export function scaleValue(value: number, maximum: number, length: number): number {
  if (maximum <= 0) {
    return 0;
  }

  return (Math.max(value, 0) / maximum) * length;
}
