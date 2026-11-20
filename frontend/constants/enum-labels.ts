// Display labels for the enums shown in the UI.
// Typed records make the build fail when an enum member has no label.

import { ServiceRequestStatus } from "@/enums/service-request-status";
import { ServiceRequestTag } from "@/enums/service-request-tag";
import { UserRole } from "@/enums/user-role";

export const USER_ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.Admin]: "Admin",
  [UserRole.Technician]: "Technician",
  [UserRole.Requester]: "Requester",
};

export const SERVICE_REQUEST_STATUS_LABELS: Record<ServiceRequestStatus, string> = {
  [ServiceRequestStatus.Open]: "Open",
  [ServiceRequestStatus.Assigned]: "Assigned",
  [ServiceRequestStatus.InProgress]: "In progress",
  [ServiceRequestStatus.Resolved]: "Resolved",
};

export const SERVICE_REQUEST_TAG_LABELS: Record<ServiceRequestTag, string> = {
  [ServiceRequestTag.Hardware]: "Hardware",
  [ServiceRequestTag.Software]: "Software",
  [ServiceRequestTag.Network]: "Network",
  [ServiceRequestTag.Other]: "Other",
};
