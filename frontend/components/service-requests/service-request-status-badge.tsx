// Badge that shows the status of a service request with its label and colour tone.
// Shared by the request lists and the request detail page.

import { Badge } from "@/components/shared/badge";
import { SERVICE_REQUEST_STATUS_LABELS } from "@/constants/enum-labels";
import type { ServiceRequestStatus } from "@/enums/service-request-status";
import { SERVICE_REQUEST_STATUS_TONES } from "@/lib/service-requests/status-tone";

interface ServiceRequestStatusBadgeProps {
  status: ServiceRequestStatus;
}

export function ServiceRequestStatusBadge({ status }: ServiceRequestStatusBadgeProps) {
  return (
    <Badge tone={SERVICE_REQUEST_STATUS_TONES[status]}>{SERVICE_REQUEST_STATUS_LABELS[status]}</Badge>
  );
}
