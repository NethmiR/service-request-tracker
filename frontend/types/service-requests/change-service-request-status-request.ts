// Body of the call that changes the status of a service request.
// The remark is optional and is sent as null when left blank.

import type { ServiceRequestStatus } from "@/enums/service-request-status";

export interface ChangeServiceRequestStatusRequest {
  status: ServiceRequestStatus;
  remark: string | null;
}
