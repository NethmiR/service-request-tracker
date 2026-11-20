// New request page where any signed in user raises a service request.
// The form lives in a client component because it holds the field values and submits them.

import type { Metadata } from "next";
import { CreateServiceRequestForm } from "@/components/service-requests/create-service-request-form";
import { ContentSection } from "@/components/shared/content-section";
import { PageHeader } from "@/components/shared/page-header";
import { ServiceRequestMessage } from "@/constants/service-request-messages";

export const metadata: Metadata = {
  title: ServiceRequestMessage.NewRequestTitle,
};

export default function NewServiceRequestPage() {
  return (
    <>
      <PageHeader
        title={ServiceRequestMessage.NewRequestTitle}
        description={ServiceRequestMessage.NewRequestDescription}
      />
      <ContentSection
        title={ServiceRequestMessage.FormTitle}
        description={ServiceRequestMessage.FormDescription}
        className="max-w-3xl"
      >
        <CreateServiceRequestForm />
      </ContentSection>
    </>
  );
}
