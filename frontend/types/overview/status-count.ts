// Number of service requests in one status for an overview count box, mirrored from the backend StatusCountResource.
// The status is a value item, so no status name is ever used as a key.

import type { ServiceRequestStatus } from "@/enums/service-request-status";

export interface StatusCount {
  status: ServiceRequestStatus;
  count: number;
}
