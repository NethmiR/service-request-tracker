// Loads one page of service request summaries with the given filters through the shared paginated hook.
// Each filter is a dependency of the page loader, so changing any of them returns to page one.

"use client";

import { useCallback } from "react";
import {
  usePaginatedResource,
  type PaginatedResource,
} from "@/hooks/shared/use-paginated-resource";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

export type ServiceRequestSummaryPageLoader = (
  page: number,
  filters: ServiceRequestFilters,
) => Promise<PaginatedResponse<ServiceRequestSummary>>;

export function useServiceRequestSummaries(
  loadPage: ServiceRequestSummaryPageLoader,
  filters: ServiceRequestFilters,
): PaginatedResource<ServiceRequestSummary> {
  const { status, assignee_id: assigneeId, priority, search } = filters;

  const loadFilteredPage = useCallback(
    (page: number) => loadPage(page, { status, assignee_id: assigneeId, priority, search }),
    [loadPage, status, assigneeId, priority, search],
  );

  return usePaginatedResource(loadFilteredPage);
}
