// Request dashboard page where technicians and admins see every service request, newest first.
// The paginated list and the assign to me dialog live in a client panel because they load data and hold state.

import type { Metadata } from "next";
import { ServiceRequestsDashboardPanel } from "@/components/service-requests/service-requests-dashboard-panel";
import { PageHeader } from "@/components/shared/page-header";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";

export const metadata: Metadata = {
  title: ServiceRequestWorkflowMessage.DashboardTitle,
};

export default function ServiceRequestsDashboardPage() {
  return (
    <>
      <PageHeader
        title={ServiceRequestWorkflowMessage.DashboardTitle}
        description={ServiceRequestWorkflowMessage.DashboardDescription}
      />
      <ServiceRequestsDashboardPanel />
    </>
  );
}
