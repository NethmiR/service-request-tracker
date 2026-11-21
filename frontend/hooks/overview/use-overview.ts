// Loads every figure of the overview page and reloads it on demand.
// The loaded figures stay on screen while a reload runs, so refreshing never empties the page.

"use client";

import { useResource, type ResourceResult } from "@/hooks/shared/use-resource";
import { overviewService } from "@/services/overview/overview-service";
import type { Overview } from "@/types/overview/overview";

function loadOverview(): Promise<Overview> {
  return overviewService.getOverview();
}

export function useOverview(): ResourceResult<Overview> {
  return useResource(loadOverview);
}
