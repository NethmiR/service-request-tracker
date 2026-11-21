// Workflow actions the viewing user may take on a service request, mirrored from the backend resource.
// The frontend shows workflow controls only from these fields and never repeats the rules.

import type { ServiceRequestStatus } from "@/enums/service-request-status";

export interface AvailableActions {
  can_assign_to_self: boolean;
  can_reassign: boolean;
  allowed_statuses: ServiceRequestStatus[];
}
