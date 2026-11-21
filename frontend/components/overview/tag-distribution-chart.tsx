// Chart section with the number of requests created in the reporting window that carry each tag.
// Tags arrive in enum order and are labelled through the shared tag labels.

import type { ChartSeries } from "@/components/shared/charts/chart-series";
import { ColumnChart } from "@/components/shared/charts/column-chart";
import { ContentSection } from "@/components/shared/content-section";
import { SERVICE_REQUEST_TAG_LABELS } from "@/constants/enum-labels";
import { OverviewMessage } from "@/constants/overview-messages";
import { ChartTone } from "@/enums/ui/chart-tone";
import { OverviewChartSeries } from "@/enums/ui/overview-chart-series";
import type { TagCount } from "@/types/overview/tag-count";

const TAG_DISTRIBUTION_SERIES: readonly ChartSeries<TagCount>[] = [
  {
    key: OverviewChartSeries.Requests,
    label: OverviewMessage.RequestsAxis,
    tone: ChartTone.Primary,
    value: (tagCount) => tagCount.count,
  },
];

interface TagDistributionChartProps {
  tagCounts: readonly TagCount[];
}

export function TagDistributionChart({ tagCounts }: TagDistributionChartProps) {
  return (
    <ContentSection
      title={OverviewMessage.TagDistributionTitle}
      description={OverviewMessage.TagDistributionDescription}
    >
      <ColumnChart
        data={tagCounts}
        categoryKey={(tagCount) => tagCount.tag}
        categoryLabel={(tagCount) => SERVICE_REQUEST_TAG_LABELS[tagCount.tag]}
        categoryHeader={OverviewMessage.TagColumn}
        series={TAG_DISTRIBUTION_SERIES}
        missingValueText={OverviewMessage.NoDataInPeriod}
        title={OverviewMessage.TagDistributionTitle}
        valueAxisLabel={OverviewMessage.RequestsAxis}
        tableCaption={OverviewMessage.TagDistributionTableCaption}
      />
    </ContentSection>
  );
}
