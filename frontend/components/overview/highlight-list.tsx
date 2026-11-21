// List of requests in one overview highlight, each linking to its detail page with its status, people and one timestamp.
// The timestamp prop chooses between the creation time and the latest status change, and an empty list shows its own text.

import Link from "next/link";
import { ServiceRequestStatusBadge } from "@/components/service-requests/service-request-status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { OverviewMessage } from "@/constants/overview-messages";
import { serviceRequestDetailRoute } from "@/constants/routes";
import {
  ServiceRequestMessage,
  formatRequestReference,
} from "@/constants/service-request-messages";
import { HighlightTimestamp } from "@/enums/ui/highlight-timestamp";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { OverviewRequest } from "@/types/overview/overview-request";

interface TimestampField {
  label: string;
  value: (serviceRequest: OverviewRequest) => string;
}

const TIMESTAMP_FIELDS: Record<HighlightTimestamp, TimestampField> = {
  [HighlightTimestamp.CreatedAt]: {
    label: OverviewMessage.CreatedLabel,
    value: (serviceRequest) => serviceRequest.created_at,
  },
  [HighlightTimestamp.LastStatusChangedAt]: {
    label: OverviewMessage.LastStatusChangeLabel,
    value: (serviceRequest) => serviceRequest.last_status_changed_at,
  },
};

interface HighlightListProps {
  serviceRequests: readonly OverviewRequest[];
  timestamp: HighlightTimestamp;
  emptyText: string;
}

export function HighlightList({ serviceRequests, timestamp, emptyText }: HighlightListProps) {
  if (serviceRequests.length === 0) {
    return (
      <EmptyState
        description={emptyText}
        className="rounded-lg border border-dashed border-border py-8"
      />
    );
  }

  const timestampField = TIMESTAMP_FIELDS[timestamp];

  return (
    <ul className="flex flex-col divide-y divide-border-subtle">
      {serviceRequests.map((serviceRequest) => {
        const timestampValue = timestampField.value(serviceRequest);

        return (
          <li
            key={serviceRequest.id}
            className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="flex min-w-0 flex-col gap-0.5">
                <Link
                  href={serviceRequestDetailRoute(serviceRequest.id)}
                  className="font-medium break-words text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary"
                >
                  {serviceRequest.title}
                </Link>
                <span className="text-xs text-muted">
                  {formatRequestReference(serviceRequest.id)}
                </span>
              </div>
              <ServiceRequestStatusBadge status={serviceRequest.status} />
            </div>
            <dl className="grid gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
              <div className="flex flex-col">
                <dt className="text-muted">{ServiceRequestMessage.RequestedByLabel}</dt>
                <dd className="text-foreground">{serviceRequest.requested_by.name}</dd>
              </div>
              <div className="flex flex-col">
                <dt className="text-muted">{ServiceRequestMessage.AssigneeLabel}</dt>
                {serviceRequest.assignee === null ? (
                  <dd className="text-muted">{ServiceRequestMessage.Unassigned}</dd>
                ) : (
                  <dd className="text-foreground">{serviceRequest.assignee.name}</dd>
                )}
              </div>
              <div className="flex flex-col">
                <dt className="text-muted">{timestampField.label}</dt>
                <dd className="text-foreground">
                  <time dateTime={timestampValue} className="whitespace-nowrap">
                    {formatDateTime(timestampValue)}
                  </time>
                </dd>
              </div>
            </dl>
          </li>
        );
      })}
    </ul>
  );
}
