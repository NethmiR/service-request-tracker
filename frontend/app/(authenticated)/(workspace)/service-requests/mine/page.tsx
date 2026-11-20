// My requests page where every user sees the requests they raised, newest first.
// The paginated list lives in a client panel because it loads data and holds the current page.

import type { Metadata } from "next";
import { MyServiceRequestsPanel } from "@/components/service-requests/my-service-requests-panel";
import { PageHeader } from "@/components/shared/page-header";
import { ServiceRequestMessage } from "@/constants/service-request-messages";

export const metadata: Metadata = {
  title: ServiceRequestMessage.MyRequestsTitle,
};

export default function MyServiceRequestsPage() {
  return (
    <>
      <PageHeader
        title={ServiceRequestMessage.MyRequestsTitle}
        description={ServiceRequestMessage.MyRequestsDescription}
      />
      <MyServiceRequestsPanel />
    </>
  );
}
