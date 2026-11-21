// Turns a raw form or select value into a service request priority.
// Returns undefined for any value that is not a member of the priority enum.

import { ServiceRequestPriority } from "@/enums/service-request-priority";

export function parseServiceRequestPriority(value: string): ServiceRequestPriority | undefined {
  return Object.values(ServiceRequestPriority).find((priority) => priority === value);
}
