// Overview page for technicians and admins, holding placeholder content for now.
// Phase 7 replaces the placeholder with the counts, highlights and charts.

import type { Metadata } from "next";
import { EmptyState } from "@/components/shared/empty-state";
import { PageHeader } from "@/components/shared/page-header";
import { AuthMessage } from "@/constants/auth-messages";

export const metadata: Metadata = {
  title: AuthMessage.OverviewTitle,
};

export default function OverviewPage() {
  return (
    <>
      <PageHeader title={AuthMessage.OverviewTitle} />
      <EmptyState
        description={AuthMessage.OverviewPlaceholder}
        className="flex-1 rounded-lg border border-dashed border-border"
      />
    </>
  );
}
