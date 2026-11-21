// Chart section comparing the requests created and resolved in each of the last twelve reporting months.
// The axis shows short month names, while the tooltips and the hidden table show the month with its year.

import { ChartLegend } from "@/components/shared/charts/chart-legend";
import type { ChartSeries } from "@/components/shared/charts/chart-series";
import { ColumnChart } from "@/components/shared/charts/column-chart";
import { ContentSection } from "@/components/shared/content-section";
import { OverviewMessage, formatTimeZoneNote } from "@/constants/overview-messages";
import { ChartTone } from "@/enums/ui/chart-tone";
import { OverviewChartSeries } from "@/enums/ui/overview-chart-series";
import {
  formatReportingMonth,
  formatReportingMonthShort,
} from "@/lib/overview/format-reporting-month";
import type { MonthlyTrend } from "@/types/overview/monthly-trend";

const REQUEST_VOLUME_SERIES: readonly ChartSeries<MonthlyTrend>[] = [
  {
    key: OverviewChartSeries.Created,
    label: OverviewMessage.CreatedSeries,
    tone: ChartTone.Primary,
    value: (trend) => trend.created,
  },
  {
    key: OverviewChartSeries.Resolved,
    label: OverviewMessage.ResolvedSeries,
    tone: ChartTone.Success,
    value: (trend) => trend.resolved,
  },
];

interface RequestVolumeChartProps {
  monthlyTrends: readonly MonthlyTrend[];
  timeZone: string;
}

export function RequestVolumeChart({ monthlyTrends, timeZone }: RequestVolumeChartProps) {
  return (
    <ContentSection
      title={OverviewMessage.RequestVolumeTitle}
      description={OverviewMessage.RequestVolumeDescription}
    >
      <ChartLegend items={REQUEST_VOLUME_SERIES} />
      <ColumnChart
        data={monthlyTrends}
        categoryKey={(trend) => trend.month}
        categoryLabel={(trend) => formatReportingMonth(trend.month)}
        axisCategoryLabel={(trend) => formatReportingMonthShort(trend.month)}
        categoryHeader={OverviewMessage.MonthColumn}
        series={REQUEST_VOLUME_SERIES}
        missingValueText={OverviewMessage.NoDataInPeriod}
        title={OverviewMessage.RequestVolumeTitle}
        valueAxisLabel={OverviewMessage.RequestsAxis}
        tableCaption={OverviewMessage.RequestVolumeTableCaption}
      />
      <p className="text-xs text-muted">{formatTimeZoneNote(timeZone)}</p>
    </ContentSection>
  );
}
