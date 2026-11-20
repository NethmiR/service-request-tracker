// Loads one service request with both histories for the request detail page.
// Passes a loader keyed by the request id to the shared single resource hook.

"use client";

import { useCallback } from "react";
import { useResource, type ResourceResult } from "@/hooks/shared/use-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";

export function useServiceRequest(serviceRequestId: number): ResourceResult<ServiceRequestDetail> {
  const loadServiceRequest = useCallback(
    () => serviceRequestService.getServiceRequest(serviceRequestId),
    [serviceRequestId],
  );

  return useResource(loadServiceRequest);
}
