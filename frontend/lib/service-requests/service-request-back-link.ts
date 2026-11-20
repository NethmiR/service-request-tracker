// Chooses where the request detail page links back to for the signed in user's role.
// Technicians and admins go back to the dashboard and every other role goes back to My requests.

import { STAFF_ROLES } from "@/constants/role-groups";
import { AppRoute } from "@/constants/routes";
import { ServiceRequestMessage } from "@/constants/service-request-messages";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";
import type { UserRole } from "@/enums/user-role";

export interface ServiceRequestBackLink {
  route: AppRoute;
  label: string;
}

export function serviceRequestBackLink(role: UserRole): ServiceRequestBackLink {
  if (STAFF_ROLES.includes(role)) {
    return { route: AppRoute.ServiceRequests, label: ServiceRequestWorkflowMessage.BackToDashboard };
  }

  return { route: AppRoute.MyServiceRequests, label: ServiceRequestMessage.BackToMyRequests };
}
