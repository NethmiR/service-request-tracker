"use client";

import { useResource, type ResourceResult } from "@/hooks/shared/use-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { UserReference } from "@/types/shared/user-reference";

function loadMyServiceRequestAssignees(): Promise<UserReference[]> {
  return serviceRequestService.listMyAssignees();
}

export function useMyServiceRequestAssignees(): ResourceResult<UserReference[]> {
  return useResource(loadMyServiceRequestAssignees);
}
