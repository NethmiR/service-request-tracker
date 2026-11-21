// Every figure of the overview page in one response, mirrored from the backend OverviewResource.
// All figures are computed at the same moment, which generated_at records.

import type { MonthlyTrend } from "@/types/overview/monthly-trend";
import type { OverviewHighlights } from "@/types/overview/overview-highlights";
import type { StaffWorkload } from "@/types/overview/staff-workload";
import type { StatusCount } from "@/types/overview/status-count";
import type { TagCount } from "@/types/overview/tag-count";

export interface Overview {
  generated_at: string;
  time_zone: string;
  status_counts: StatusCount[];
  highlights: OverviewHighlights;
  monthly_trends: MonthlyTrend[];
  tag_counts: TagCount[];
  staff_workload: StaffWorkload[];
}
