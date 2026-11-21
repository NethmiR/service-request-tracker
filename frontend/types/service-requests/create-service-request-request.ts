// Body of the create service request call.
// The priority is sent only here, because it is fixed once the request is created.
// The requester and the initial status are always set by the backend, so they are never sent.

import type { ServiceRequestPriority } from "@/enums/service-request-priority";
import type { ServiceRequestTag } from "@/enums/service-request-tag";

export interface CreateServiceRequestRequest {
  title: string;
  description: string;
  tags: ServiceRequestTag[];
  priority: ServiceRequestPriority;
}
