// Loads one page of the signed in user's service requests and moves between pages.
// Passes the My requests endpoint to the shared paginated resource hook.

"use client";

import { useServiceRequestSummaries } from "@/hooks/service-requests/use-service-request-summaries";
import type { PaginatedResource } from "@/hooks/shared/use-paginated-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

function loadMyServiceRequestsPage(
  page: number,
  filters: ServiceRequestFilters,
): Promise<PaginatedResponse<ServiceRequestSummary>> {
  return serviceRequestService.listMyServiceRequests(page, filters);
}

export function useMyServiceRequests(
  filters: ServiceRequestFilters,
): PaginatedResource<ServiceRequestSummary> {
  return useServiceRequestSummaries(loadMyServiceRequestsPage, filters);
}
