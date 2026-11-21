// Count boxes of the overview page, one card for each counted status in the order the API returns them.
// Labels come from the shared status labels, so no status name is written here.

import { StatCard } from "@/components/shared/stat-card";
import { SERVICE_REQUEST_STATUS_LABELS } from "@/constants/enum-labels";
import { OverviewMessage } from "@/constants/overview-messages";
import { ServiceRequestStatus } from "@/enums/service-request-status";
import type { StatusCount } from "@/types/overview/status-count";

const STATUS_COUNT_DESCRIPTIONS: Readonly<Partial<Record<ServiceRequestStatus, string>>> = {
  [ServiceRequestStatus.Open]: OverviewMessage.OpenCountDescription,
  [ServiceRequestStatus.Assigned]: OverviewMessage.AssignedCountDescription,
  [ServiceRequestStatus.InProgress]: OverviewMessage.InProgressCountDescription,
};

interface StatusCountCardsProps {
  statusCounts: readonly StatusCount[];
}

export function StatusCountCards({ statusCounts }: StatusCountCardsProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-3">
      {statusCounts.map((item) => (
        <StatCard
          key={item.status}
          label={SERVICE_REQUEST_STATUS_LABELS[item.status]}
          value={item.count}
          description={STATUS_COUNT_DESCRIPTIONS[item.status]}
        />
      ))}
    </div>
  );
}
