// One service request as returned in a paginated list, mirrored from the backend summary resource.
// The assignee comes from the latest assignee history record and is null while unassigned.
// The available actions tell which workflow controls the viewing user may see.

import type { ServiceRequestStatus } from "@/enums/service-request-status";
import type { ServiceRequestTag } from "@/enums/service-request-tag";
import type { AvailableActions } from "@/types/service-requests/available-actions";
import type { UserReference } from "@/types/shared/user-reference";

export interface ServiceRequestSummary {
  id: number;
  title: string;
  status: ServiceRequestStatus;
  tags: ServiceRequestTag[];
  requested_by: UserReference;
  assignee: UserReference | null;
  created_at: string;
  available_actions: AvailableActions;
}
