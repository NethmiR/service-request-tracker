// Read only badge that shows the priority of a service request with its label and colour tone.
// Shared by the request lists and the request detail page, and never offers a way to change the priority.

import { Badge } from "@/components/shared/badge";
import { SERVICE_REQUEST_PRIORITY_LABELS } from "@/constants/enum-labels";
import type { ServiceRequestPriority } from "@/enums/service-request-priority";
import { SERVICE_REQUEST_PRIORITY_TONES } from "@/lib/service-requests/priority-tone";

interface ServiceRequestPriorityBadgeProps {
  priority: ServiceRequestPriority;
}

export function ServiceRequestPriorityBadge({ priority }: ServiceRequestPriorityBadgeProps) {
  return (
    <Badge tone={SERVICE_REQUEST_PRIORITY_TONES[priority]}>
      {SERVICE_REQUEST_PRIORITY_LABELS[priority]}
    </Badge>
  );
}
