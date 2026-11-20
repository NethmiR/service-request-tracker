// Main section of the request detail page with the title, status, tags, people involved and description.
// The description is plain text with its line breaks kept, so no user supplied HTML is ever rendered.

import type { ReactNode } from "react";
import { ServiceRequestStatusBadge } from "@/components/service-requests/service-request-status-badge";
import { ServiceRequestTags } from "@/components/service-requests/service-request-tags";
import { ContentSection } from "@/components/shared/content-section";
import {
  ServiceRequestMessage,
  formatRequestReference,
} from "@/constants/service-request-messages";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";

interface DetailItemProps {
  label: string;
  children: ReactNode;
}

function DetailItem({ label, children }: DetailItemProps) {
  return (
    <div className="flex min-w-0 flex-col items-start gap-1">
      <dt className="text-sm font-medium text-muted">{label}</dt>
      <dd className="max-w-full text-base break-words text-foreground">{children}</dd>
    </div>
  );
}

interface ServiceRequestDetailsProps {
  serviceRequest: ServiceRequestDetail;
}

export function ServiceRequestDetails({ serviceRequest }: ServiceRequestDetailsProps) {
  return (
    <ContentSection
      title={serviceRequest.title}
      description={formatRequestReference(serviceRequest.id)}
      actions={<ServiceRequestStatusBadge status={serviceRequest.status} />}
      className="break-words"
    >
      <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <DetailItem label={ServiceRequestMessage.RequestedByLabel}>
          {serviceRequest.requested_by.name}
        </DetailItem>
        <DetailItem label={ServiceRequestMessage.AssigneeLabel}>
          {serviceRequest.assignee === null ? (
            <span className="text-muted">{ServiceRequestMessage.Unassigned}</span>
          ) : (
            serviceRequest.assignee.name
          )}
        </DetailItem>
        <DetailItem label={ServiceRequestMessage.CreatedLabel}>
          <time dateTime={serviceRequest.created_at}>
            {formatDateTime(serviceRequest.created_at)}
          </time>
        </DetailItem>
        <DetailItem label={ServiceRequestMessage.TagsLabel}>
          <ServiceRequestTags tags={serviceRequest.tags} />
        </DetailItem>
      </dl>
      <div className="flex flex-col gap-2 border-t border-border-subtle pt-5">
        <h3 className="text-sm font-medium text-muted">{ServiceRequestMessage.DescriptionLabel}</h3>
        <p className="text-base leading-relaxed whitespace-pre-wrap break-words text-foreground">
          {serviceRequest.description}
        </p>
      </div>
    </ContentSection>
  );
}
