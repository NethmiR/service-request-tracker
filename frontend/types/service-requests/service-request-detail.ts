// One service request with its description and both histories, mirrored from the backend detail resource.
// Both histories arrive newest first and are shown in that order.

import type { AssigneeHistoryEntry } from "@/types/service-requests/assignee-history-entry";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { StatusHistoryEntry } from "@/types/service-requests/status-history-entry";

export interface ServiceRequestDetail extends ServiceRequestSummary {
  description: string;
  status_history: StatusHistoryEntry[];
  assignee_history: AssigneeHistoryEntry[];
}
