// The four highlight lists of the overview page together with the stale threshold in days.
// Mirrors the highlights part of the backend OverviewResource.

import type { OverviewRequest } from "@/types/overview/overview-request";

export interface OverviewHighlights {
  stale_after_days: number;
  oldest_open: OverviewRequest[];
  assigned_to_viewer: OverviewRequest[];
  stale: OverviewRequest[];
  recently_resolved: OverviewRequest[];
}
