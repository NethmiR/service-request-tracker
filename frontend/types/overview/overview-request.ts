// One service request in an overview highlight list, mirrored from the backend OverviewRequestResource.
// The assignee is null for an unassigned request, and the last status change is the time of its latest status record.

import type { ServiceRequestStatus } from "@/enums/service-request-status";
import type { UserReference } from "@/types/shared/user-reference";

export interface OverviewRequest {
  id: number;
  title: string;
  status: ServiceRequestStatus;
  requested_by: UserReference;
  assignee: UserReference | null;
  created_at: string;
  last_status_changed_at: string;
}
