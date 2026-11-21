// Text for the overview page, its count boxes, highlight lists and charts.
// Also formats the update time, the stale list description, the time zone note and the average resolution time.

import { DISPLAY_LOCALE } from "@/constants/application";
import { formatDateTime } from "@/lib/utilities/format-date-time";

export const OverviewMessage = {
  PageTitle: "Overview",
  PageDescription: "The current state of IT service requests and how it has changed over the last year.",
  Refresh: "Refresh",
  LoadFailed: "The overview could not be loaded.",
  OpenCountDescription: "Waiting for a member of IT Support to pick them up.",
  AssignedCountDescription: "Assigned and waiting for work to start.",
  InProgressCountDescription: "Being worked on right now.",
  HighlightsTitle: "Highlights",
  OldestOpenTitle: "Oldest open requests",
  OldestOpenDescription: "Open requests that have waited longest for assignment.",
  OldestOpenEmpty: "No open requests are waiting for assignment.",
  AssignedToViewerTitle: "Assigned to you",
  AssignedToViewerDescription: "Your assigned and in progress requests, oldest first.",
  AssignedToViewerEmpty: "You have no unresolved requests assigned to you.",
  StaleTitle: "No recent status change",
  StaleEmpty: "Every unresolved request has changed status recently.",
  RecentlyResolvedTitle: "Recently resolved",
  RecentlyResolvedDescription: "The most recently resolved requests.",
  RecentlyResolvedEmpty: "No requests have been resolved yet.",
  CreatedLabel: "Created",
  LastStatusChangeLabel: "Last status change",
  RequestVolumeTitle: "Created and resolved requests",
  RequestVolumeDescription: "Requests created and resolved each month over the last twelve months.",
  RequestVolumeTableCaption: "Requests created and resolved per month",
  ResolutionTimeTitle: "Average resolution time",
  ResolutionTimeDescription:
    "Average time from creation to resolution for the requests resolved each month.",
  ResolutionTimeTableCaption: "Average resolution time in hours per month",
  TagDistributionTitle: "Requests by tag",
  TagDistributionDescription:
    "Requests created in the last twelve months that carry each tag. A request with several tags counts under each one.",
  TagDistributionTableCaption: "Requests per tag",
  StaffWorkloadTitle: "Current workload",
  StaffWorkloadDescription: "Assigned and in progress requests held by each technician and admin.",
  StaffWorkloadTableCaption: "Assigned and in progress requests per staff member",
  StaffWorkloadEmptyTitle: "No staff members",
  StaffWorkloadEmptyDescription: "There are no technicians or admins to show.",
  CreatedSeries: "Created",
  ResolvedSeries: "Resolved",
  AverageResolutionSeries: "Average resolution time",
  AssignedSeries: "Assigned",
  InProgressSeries: "In progress",
  RequestsAxis: "Requests",
  HoursAxis: "Hours",
  MonthColumn: "Month",
  TagColumn: "Tag",
  StaffMemberColumn: "Staff member",
  NoDataInPeriod: "No data in this period",
} as const;

const HOURS_FRACTION_DIGITS = 1;

const hoursFormatter = new Intl.NumberFormat(DISPLAY_LOCALE, {
  maximumFractionDigits: HOURS_FRACTION_DIGITS,
});

export function formatGeneratedAt(isoTimestamp: string): string {
  return `Updated ${formatDateTime(isoTimestamp)}`;
}

export function formatStaleDescription(days: number): string {
  const unit = days === 1 ? "day" : "days";

  return `Unresolved requests with no status change for more than ${days} ${unit}.`;
}

export function formatTimeZoneNote(timeZone: string): string {
  return `Months follow the ${timeZone} time zone.`;
}

export function formatAverageResolution(hours: number | null): string {
  if (hours === null) {
    return OverviewMessage.NoDataInPeriod;
  }

  const unit = hours === 1 ? "hour" : "hours";

  return `${hoursFormatter.format(hours)} ${unit}`;
}
