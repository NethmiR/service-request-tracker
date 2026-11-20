// One status history record of a service request, mirrored from the backend resource.
// The from status and the actor are null only for the initial record written when the request is created.

import type { ServiceRequestStatus } from "@/enums/service-request-status";
import type { UserReference } from "@/types/shared/user-reference";

export interface StatusHistoryEntry {
  id: number;
  from_status: ServiceRequestStatus | null;
  to_status: ServiceRequestStatus;
  changed_by: UserReference | null;
  remark: string | null;
  created_at: string;
}
