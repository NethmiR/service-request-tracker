// Loads every technician and admin a service request can be reassigned to.
// The reassign dialog mounts its form only while open, so the list is loaded fresh each time it opens.

"use client";

import { useResource, type ResourceResult } from "@/hooks/shared/use-resource";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { UserReference } from "@/types/shared/user-reference";

function loadServiceRequestAssignees(): Promise<UserReference[]> {
  return serviceRequestService.listAssignees();
}

export function useServiceRequestAssignees(): ResourceResult<UserReference[]> {
  return useResource(loadServiceRequestAssignees);
}
