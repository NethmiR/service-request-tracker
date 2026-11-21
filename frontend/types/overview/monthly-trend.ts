// Created and resolved figures of one reporting month, mirrored from the backend MonthlyTrendResource.
// The month is a YYYY-MM key, and the average resolution time is null for a month with no resolution.

export interface MonthlyTrend {
  month: string;
  created: number;
  resolved: number;
  average_resolution_hours: number | null;
}
