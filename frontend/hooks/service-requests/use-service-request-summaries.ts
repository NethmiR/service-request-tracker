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
  const { status, assignee_id: assigneeId } = filters;

  const loadFilteredPage = useCallback(
    (page: number) => loadPage(page, { status, assignee_id: assigneeId }),
    [loadPage, status, assigneeId],
  );

  return usePaginatedResource(loadFilteredPage);
}
