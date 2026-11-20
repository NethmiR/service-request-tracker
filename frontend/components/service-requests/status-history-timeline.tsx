// Section of the request detail page that lists the status history newest first, as the API returns it.
// The initial record has no actor and is described as recorded when the request was created.

import { ContentSection } from "@/components/shared/content-section";
import { Timeline, type TimelineItem } from "@/components/shared/timeline";
import {
  ServiceRequestMessage,
  formatChangedBy,
  formatInitialStatus,
  formatStatusChange,
} from "@/constants/service-request-messages";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { StatusHistoryEntry } from "@/types/service-requests/status-history-entry";

function toTimelineItem(entry: StatusHistoryEntry): TimelineItem {
  const base = {
    key: entry.id,
    dateTime: entry.created_at,
    formattedDateTime: formatDateTime(entry.created_at),
    body: entry.remark ?? undefined,
  };

  if (entry.from_status === null || entry.changed_by === null) {
    return {
      ...base,
      heading: formatInitialStatus(entry.to_status),
      meta: ServiceRequestMessage.InitialStatusMeta,
    };
  }

  return {
    ...base,
    heading: formatStatusChange(entry.from_status, entry.to_status),
    meta: formatChangedBy(entry.changed_by.name),
  };
}

interface StatusHistoryTimelineProps {
  entries: StatusHistoryEntry[];
}

export function StatusHistoryTimeline({ entries }: StatusHistoryTimelineProps) {
  return (
    <ContentSection
      title={ServiceRequestMessage.StatusHistoryTitle}
      description={ServiceRequestMessage.StatusHistoryDescription}
    >
      <Timeline
        items={entries.map(toTimelineItem)}
        bodyLabel={ServiceRequestMessage.RemarkLabel}
        emptyTitle={ServiceRequestMessage.StatusHistoryEmptyTitle}
        emptyDescription={ServiceRequestMessage.StatusHistoryEmptyDescription}
      />
    </ContentSection>
  );
}
