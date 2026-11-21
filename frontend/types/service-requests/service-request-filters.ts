import type { ServiceRequestStatus } from "@/enums/service-request-status";

export interface ServiceRequestFilters {
  status?: ServiceRequestStatus;
  assignee_id?: number;
}
