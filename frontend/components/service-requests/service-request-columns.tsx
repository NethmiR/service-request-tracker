// Column definitions shared by the service request tables, one export per column.
// The title links to the request detail page, and Phase 6 builds the dashboard from the same definitions.

import Link from "next/link";
import { ServiceRequestStatusBadge } from "@/components/service-requests/service-request-status-badge";
import { ServiceRequestTags } from "@/components/service-requests/service-request-tags";
import type { TableColumn } from "@/components/shared/table";
import { serviceRequestDetailRoute } from "@/constants/routes";
import {
  ServiceRequestMessage,
  formatRequestReference,
} from "@/constants/service-request-messages";
import { ServiceRequestTableColumn } from "@/enums/ui/service-request-table-column";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";

export const SERVICE_REQUEST_TITLE_COLUMN: TableColumn<ServiceRequestSummary> = {
  key: ServiceRequestTableColumn.Title,
  header: ServiceRequestMessage.TitleColumn,
  className: "min-w-56",
  cell: (serviceRequest) => (
    <div className="flex flex-col gap-0.5">
      <Link
        href={serviceRequestDetailRoute(serviceRequest.id)}
        className="font-medium break-words text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary"
      >
        {serviceRequest.title}
      </Link>
      <span className="text-xs text-muted">{formatRequestReference(serviceRequest.id)}</span>
    </div>
  ),
};

export const SERVICE_REQUEST_TAGS_COLUMN: TableColumn<ServiceRequestSummary> = {
  key: ServiceRequestTableColumn.Tags,
  header: ServiceRequestMessage.TagsColumn,
  cell: (serviceRequest) => <ServiceRequestTags tags={serviceRequest.tags} />,
};

export const SERVICE_REQUEST_STATUS_COLUMN: TableColumn<ServiceRequestSummary> = {
  key: ServiceRequestTableColumn.Status,
  header: ServiceRequestMessage.StatusColumn,
  cell: (serviceRequest) => <ServiceRequestStatusBadge status={serviceRequest.status} />,
};

export const SERVICE_REQUEST_ASSIGNEE_COLUMN: TableColumn<ServiceRequestSummary> = {
  key: ServiceRequestTableColumn.Assignee,
  header: ServiceRequestMessage.AssigneeColumn,
  cell: (serviceRequest) =>
    serviceRequest.assignee === null ? (
      <span className="text-muted">{ServiceRequestMessage.Unassigned}</span>
    ) : (
      <span className="text-foreground">{serviceRequest.assignee.name}</span>
    ),
};

export const SERVICE_REQUEST_CREATED_COLUMN: TableColumn<ServiceRequestSummary> = {
  key: ServiceRequestTableColumn.CreatedAt,
  header: ServiceRequestMessage.CreatedColumn,
  cell: (serviceRequest) => (
    <time dateTime={serviceRequest.created_at} className="whitespace-nowrap">
      {formatDateTime(serviceRequest.created_at)}
    </time>
  ),
};
