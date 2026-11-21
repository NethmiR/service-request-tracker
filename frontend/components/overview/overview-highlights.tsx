// Highlights section of the overview page with the four lists of requests to review, two per row on wide screens.
// Oldest open and assigned to you show the creation time, while stale and recently resolved show the last status change.

import { useId } from "react";
import { HighlightList } from "@/components/overview/highlight-list";
import { ContentSection } from "@/components/shared/content-section";
import { OverviewMessage, formatStaleDescription } from "@/constants/overview-messages";
import { HighlightTimestamp } from "@/enums/ui/highlight-timestamp";
import type { OverviewHighlights as OverviewHighlightsData } from "@/types/overview/overview-highlights";

interface OverviewHighlightsProps {
  highlights: OverviewHighlightsData;
}

export function OverviewHighlights({ highlights }: OverviewHighlightsProps) {
  const headingId = useId();

  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-4">
      <h2 id={headingId} className="text-xl font-semibold text-primary">
        {OverviewMessage.HighlightsTitle}
      </h2>
      <div className="grid gap-6 lg:grid-cols-2">
        <ContentSection
          title={OverviewMessage.OldestOpenTitle}
          description={OverviewMessage.OldestOpenDescription}
        >
          <HighlightList
            serviceRequests={highlights.oldest_open}
            timestamp={HighlightTimestamp.CreatedAt}
            emptyText={OverviewMessage.OldestOpenEmpty}
          />
        </ContentSection>
        <ContentSection
          title={OverviewMessage.AssignedToViewerTitle}
          description={OverviewMessage.AssignedToViewerDescription}
        >
          <HighlightList
            serviceRequests={highlights.assigned_to_viewer}
            timestamp={HighlightTimestamp.CreatedAt}
            emptyText={OverviewMessage.AssignedToViewerEmpty}
          />
        </ContentSection>
        <ContentSection
          title={OverviewMessage.StaleTitle}
          description={formatStaleDescription(highlights.stale_after_days)}
        >
          <HighlightList
            serviceRequests={highlights.stale}
            timestamp={HighlightTimestamp.LastStatusChangedAt}
            emptyText={OverviewMessage.StaleEmpty}
          />
        </ContentSection>
        <ContentSection
          title={OverviewMessage.RecentlyResolvedTitle}
          description={OverviewMessage.RecentlyResolvedDescription}
        >
          <HighlightList
            serviceRequests={highlights.recently_resolved}
            timestamp={HighlightTimestamp.LastStatusChangedAt}
            emptyText={OverviewMessage.RecentlyResolvedEmpty}
          />
        </ContentSection>
      </div>
    </section>
  );
}
