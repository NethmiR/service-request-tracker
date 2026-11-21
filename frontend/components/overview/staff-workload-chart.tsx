// Chart section with the assigned and in progress requests each technician and admin currently holds, stacked per person.
// Long names are shortened on the axis only, and an empty staff list shows an empty state.

import type { ChartSeries } from "@/components/shared/charts/chart-series";
import { ChartLegend } from "@/components/shared/charts/chart-legend";
import { StackedBarChart } from "@/components/shared/charts/stacked-bar-chart";
import { ContentSection } from "@/components/shared/content-section";
import { EmptyState } from "@/components/shared/empty-state";
import { STACKED_BAR_LABEL_MAX_CHARACTERS } from "@/constants/charts";
import { OverviewMessage } from "@/constants/overview-messages";
import { ChartTone } from "@/enums/ui/chart-tone";
import { OverviewChartSeries } from "@/enums/ui/overview-chart-series";
import { shortenChartLabel } from "@/lib/charts/shorten-chart-label";
import type { StaffWorkload } from "@/types/overview/staff-workload";

const STAFF_WORKLOAD_SERIES: readonly ChartSeries<StaffWorkload>[] = [
  {
    key: OverviewChartSeries.Assigned,
    label: OverviewMessage.AssignedSeries,
    tone: ChartTone.Muted,
    value: (workload) => workload.assigned,
  },
  {
    key: OverviewChartSeries.InProgress,
    label: OverviewMessage.InProgressSeries,
    tone: ChartTone.Warning,
    value: (workload) => workload.in_progress,
  },
];

interface StaffWorkloadChartProps {
  staffWorkload: readonly StaffWorkload[];
}

export function StaffWorkloadChart({ staffWorkload }: StaffWorkloadChartProps) {
  return (
    <ContentSection
      title={OverviewMessage.StaffWorkloadTitle}
      description={OverviewMessage.StaffWorkloadDescription}
    >
      {staffWorkload.length === 0 ? (
        <EmptyState
          title={OverviewMessage.StaffWorkloadEmptyTitle}
          description={OverviewMessage.StaffWorkloadEmptyDescription}
          className="flex-1 rounded-lg border border-dashed border-border"
        />
      ) : (
        <>
          <ChartLegend items={STAFF_WORKLOAD_SERIES} />
          <StackedBarChart
            data={staffWorkload}
            categoryKey={(workload) => String(workload.user.id)}
            categoryLabel={(workload) => workload.user.name}
            axisCategoryLabel={(workload) =>
              shortenChartLabel(workload.user.name, STACKED_BAR_LABEL_MAX_CHARACTERS)
            }
            categoryHeader={OverviewMessage.StaffMemberColumn}
            series={STAFF_WORKLOAD_SERIES}
            missingValueText={OverviewMessage.NoDataInPeriod}
            title={OverviewMessage.StaffWorkloadTitle}
            valueAxisLabel={OverviewMessage.RequestsAxis}
            tableCaption={OverviewMessage.StaffWorkloadTableCaption}
          />
        </>
      )}
    </ContentSection>
  );
}
