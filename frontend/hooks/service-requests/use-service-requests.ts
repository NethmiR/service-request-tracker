// Loads one page of every service request for the technician and admin dashboard and moves between pages.
// Passes the dashboard endpoint to the shared paginated resource hook.

"use client";

import {
  usePaginatedResource,
  type PaginatedResource,
} from "@/hooks/shared/use-paginated-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

function loadServiceRequestsPage(page: number): Promise<PaginatedResponse<ServiceRequestSummary>> {
  return serviceRequestService.listServiceRequests(page);
}

export function useServiceRequests(): PaginatedResource<ServiceRequestSummary> {
  return usePaginatedResource(loadServiceRequestsPage);
}
