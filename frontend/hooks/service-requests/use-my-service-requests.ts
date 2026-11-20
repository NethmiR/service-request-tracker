// Loads one page of the signed in user's service requests and moves between pages.
// Passes the My requests endpoint to the shared paginated resource hook.

"use client";

import {
  usePaginatedResource,
  type PaginatedResource,
} from "@/hooks/shared/use-paginated-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

function loadMyServiceRequestsPage(page: number): Promise<PaginatedResponse<ServiceRequestSummary>> {
  return serviceRequestService.listMyServiceRequests(page);
}

export function useMyServiceRequests(): PaginatedResource<ServiceRequestSummary> {
  return usePaginatedResource(loadMyServiceRequestsPage);
}
