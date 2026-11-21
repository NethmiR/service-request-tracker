// Chart section with the average resolution time in hours for each of the last twelve reporting months.
// A month with no resolution breaks the line, and a year with no resolution at all shows a message in place of the chart.

import type { ChartSeries } from "@/components/shared/charts/chart-series";
import { LineChart } from "@/components/shared/charts/line-chart";
import { ContentSection } from "@/components/shared/content-section";
import { EmptyState } from "@/components/shared/empty-state";
import { OverviewMessage } from "@/constants/overview-messages";
import { ChartTone } from "@/enums/ui/chart-tone";
import { OverviewChartSeries } from "@/enums/ui/overview-chart-series";
import {
  formatReportingMonth,
  formatReportingMonthShort,
} from "@/lib/overview/format-reporting-month";
import type { MonthlyTrend } from "@/types/overview/monthly-trend";

const RESOLUTION_TIME_SERIES: readonly ChartSeries<MonthlyTrend>[] = [
  {
    key: OverviewChartSeries.AverageResolution,
    label: OverviewMessage.AverageResolutionSeries,
    tone: ChartTone.Primary,
    value: (trend) => trend.average_resolution_hours,
  },
];

interface ResolutionTimeChartProps {
  monthlyTrends: readonly MonthlyTrend[];
}

export function ResolutionTimeChart({ monthlyTrends }: ResolutionTimeChartProps) {
  const hasResolutions = monthlyTrends.some((trend) => trend.average_resolution_hours !== null);

  return (
    <ContentSection
      title={OverviewMessage.ResolutionTimeTitle}
      description={OverviewMessage.ResolutionTimeDescription}
    >
      {hasResolutions ? (
        <LineChart
          data={monthlyTrends}
          categoryKey={(trend) => trend.month}
          categoryLabel={(trend) => formatReportingMonth(trend.month)}
          axisCategoryLabel={(trend) => formatReportingMonthShort(trend.month)}
          categoryHeader={OverviewMessage.MonthColumn}
          series={RESOLUTION_TIME_SERIES}
          missingValueText={OverviewMessage.NoDataInPeriod}
          title={OverviewMessage.ResolutionTimeTitle}
          valueAxisLabel={OverviewMessage.HoursAxis}
          tableCaption={OverviewMessage.ResolutionTimeTableCaption}
        />
      ) : (
        <EmptyState
          title={OverviewMessage.NoDataInPeriod}
          description={OverviewMessage.ResolutionTimeDescription}
          className="flex-1 rounded-lg border border-dashed border-border"
        />
      )}
    </ContentSection>
  );
}
