// Request detail page that shows one service request with its status history and assignee history.
// An id that is not a positive whole number shows the not found page before any request is sent.

import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ServiceRequestDetailPanel } from "@/components/service-requests/service-request-detail-panel";
import { PageHeader } from "@/components/shared/page-header";
import { POSITIVE_WHOLE_NUMBER_PATTERN } from "@/constants/route-parameters";
import { ServiceRequestMessage } from "@/constants/service-request-messages";

export const metadata: Metadata = {
  title: ServiceRequestMessage.DetailTitle,
};

export default async function ServiceRequestDetailPage({
  params,
}: PageProps<"/service-requests/[serviceRequestId]">) {
  const { serviceRequestId } = await params;

  if (!POSITIVE_WHOLE_NUMBER_PATTERN.test(serviceRequestId)) {
    notFound();
  }

  return (
    <>
      <PageHeader
        title={ServiceRequestMessage.DetailTitle}
        description={ServiceRequestMessage.DetailDescription}
      />
      <ServiceRequestDetailPanel serviceRequestId={Number(serviceRequestId)} />
    </>
  );
}
