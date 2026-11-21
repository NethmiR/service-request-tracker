// Maps each service request priority to the badge tone it is shown with.
// The typed record makes the build fail when a priority has no tone.

import { ServiceRequestPriority } from "@/enums/service-request-priority";
import { BadgeTone } from "@/enums/ui/badge-tone";

export const SERVICE_REQUEST_PRIORITY_TONES: Record<ServiceRequestPriority, BadgeTone> = {
  [ServiceRequestPriority.Low]: BadgeTone.Neutral,
  [ServiceRequestPriority.Medium]: BadgeTone.Info,
  [ServiceRequestPriority.High]: BadgeTone.Warning,
  [ServiceRequestPriority.Critical]: BadgeTone.Danger,
};
