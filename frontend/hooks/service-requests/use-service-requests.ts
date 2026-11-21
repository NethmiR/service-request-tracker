// Loads one page of every service request for the technician and admin dashboard and moves between pages.
// Passes the dashboard endpoint to the shared paginated resource hook.

"use client";

import { useServiceRequestSummaries } from "@/hooks/service-requests/use-service-request-summaries";
import type { PaginatedResource } from "@/hooks/shared/use-paginated-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

function loadServiceRequestsPage(
  page: number,
  filters: ServiceRequestFilters,
): Promise<PaginatedResponse<ServiceRequestSummary>> {
  return serviceRequestService.listServiceRequests(page, filters);
}

export function useServiceRequests(
  filters: ServiceRequestFilters,
): PaginatedResource<ServiceRequestSummary> {
  return useServiceRequestSummaries(loadServiceRequestsPage, filters);
}
