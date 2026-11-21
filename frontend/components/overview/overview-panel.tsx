// Content of the overview page with the count boxes, the highlight lists and the four charts from one response.
// Refresh reloads every figure while the current ones stay on screen, and the toolbar shows when they were produced.

"use client";

import { useState } from "react";
import { OverviewHighlights } from "@/components/overview/overview-highlights";
import { RequestVolumeChart } from "@/components/overview/request-volume-chart";
import { ResolutionTimeChart } from "@/components/overview/resolution-time-chart";
import { StaffWorkloadChart } from "@/components/overview/staff-workload-chart";
import { StatusCountCards } from "@/components/overview/status-count-cards";
import { TagDistributionChart } from "@/components/overview/tag-distribution-chart";
import { Button } from "@/components/shared/button";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { SectionLoader } from "@/components/shared/section-loader";
import { OverviewMessage, formatGeneratedAt } from "@/constants/overview-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { useOverview } from "@/hooks/overview/use-overview";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";

export function OverviewPanel() {
  const { state, reload } = useOverview();
  const [isRefreshing, setIsRefreshing] = useState(false);

  async function handleRefresh() {
    setIsRefreshing(true);

    try {
      await reload();
    } finally {
      setIsRefreshing(false);
    }
  }

  const isRedirecting = state.status === LoadStatus.Failed && isHandledByRouteGuards(state.error);

  if (state.status === LoadStatus.Loading || isRedirecting) {
    return <SectionLoader />;
  }

  if (state.status === LoadStatus.Failed) {
    return (
      <LoadFailureAlert title={OverviewMessage.LoadFailed} error={state.error} onRetry={reload} />
    );
  }

  const overview = state.resource;

  return (
    <div className="flex flex-col gap-6" aria-busy={isRefreshing || undefined}>
      <div className="flex flex-wrap items-center justify-end gap-3">
        <p className="text-sm text-muted">
          <time dateTime={overview.generated_at}>{formatGeneratedAt(overview.generated_at)}</time>
        </p>
        <Button
          variant={ButtonVariant.Secondary}
          size={ComponentSize.Small}
          isLoading={isRefreshing}
          onClick={() => void handleRefresh()}
        >
          {OverviewMessage.Refresh}
        </Button>
      </div>
      <StatusCountCards statusCounts={overview.status_counts} />
      <OverviewHighlights highlights={overview.highlights} />
      <div className="grid gap-6 xl:grid-cols-2">
        <RequestVolumeChart monthlyTrends={overview.monthly_trends} timeZone={overview.time_zone} />
        <ResolutionTimeChart monthlyTrends={overview.monthly_trends} />
      </div>
      <div className="grid gap-6 xl:grid-cols-2">
        <TagDistributionChart tagCounts={overview.tag_counts} />
        <StaffWorkloadChart staffWorkload={overview.staff_workload} />
      </div>
    </div>
  );
}
