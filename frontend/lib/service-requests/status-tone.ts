// Maps each service request status to the badge tone it is shown with.
// The typed record makes the build fail when a status has no tone.

import { ServiceRequestStatus } from "@/enums/service-request-status";
import { BadgeTone } from "@/enums/ui/badge-tone";

export const SERVICE_REQUEST_STATUS_TONES: Record<ServiceRequestStatus, BadgeTone> = {
  [ServiceRequestStatus.Open]: BadgeTone.Info,
  [ServiceRequestStatus.Assigned]: BadgeTone.Neutral,
  [ServiceRequestStatus.InProgress]: BadgeTone.Warning,
  [ServiceRequestStatus.Resolved]: BadgeTone.Success,
};
