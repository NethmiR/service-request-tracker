// Optional filters of both service request lists, sent as query parameters with the same names.
// The backend validates each one again and keeps them in the pagination links.

import type { ServiceRequestPriority } from "@/enums/service-request-priority";
import type { ServiceRequestStatus } from "@/enums/service-request-status";

export interface ServiceRequestFilters {
  status?: ServiceRequestStatus;
  assignee_id?: number;
  priority?: ServiceRequestPriority;
  search?: string;
}
