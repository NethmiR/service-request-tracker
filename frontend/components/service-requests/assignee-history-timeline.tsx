// Section of the request detail page that lists the assignee history newest first, as the API returns it.
// The first assignment and later reassignments get their own headings, and the remark is always shown.

import { ContentSection } from "@/components/shared/content-section";
import { Timeline, type TimelineItem } from "@/components/shared/timeline";
import {
  ServiceRequestMessage,
  formatAssignment,
  formatReassignment,
} from "@/constants/service-request-messages";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { AssigneeHistoryEntry } from "@/types/service-requests/assignee-history-entry";

function toTimelineItem(entry: AssigneeHistoryEntry): TimelineItem {
  return {
    key: entry.id,
    heading:
      entry.assigned_from === null
        ? formatAssignment(entry.assigned_to.name)
        : formatReassignment(entry.assigned_from.name, entry.assigned_to.name),
    dateTime: entry.created_at,
    formattedDateTime: formatDateTime(entry.created_at),
    body: entry.remark,
  };
}

interface AssigneeHistoryTimelineProps {
  entries: AssigneeHistoryEntry[];
}

export function AssigneeHistoryTimeline({ entries }: AssigneeHistoryTimelineProps) {
  return (
    <ContentSection
      title={ServiceRequestMessage.AssigneeHistoryTitle}
      description={ServiceRequestMessage.AssigneeHistoryDescription}
    >
      <Timeline
        items={entries.map(toTimelineItem)}
        bodyLabel={ServiceRequestMessage.RemarkLabel}
        emptyTitle={ServiceRequestMessage.AssigneeHistoryEmptyTitle}
        emptyDescription={ServiceRequestMessage.AssigneeHistoryEmptyDescription}
      />
    </ContentSection>
  );
}
