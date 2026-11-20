// Body of the create service request call.
// The requester and the initial status are always set by the backend, so they are never sent.

import type { ServiceRequestTag } from "@/enums/service-request-tag";

export interface CreateServiceRequestRequest {
  title: string;
  description: string;
  tags: ServiceRequestTag[];
}
