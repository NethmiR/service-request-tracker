// My requests page where every user sees the requests they raised, holding placeholder content for now.
// Phase 5 replaces the placeholder with the paginated list of the user's requests.

import type { Metadata } from "next";
import { EmptyState } from "@/components/shared/empty-state";
import { PageHeader } from "@/components/shared/page-header";
import { AuthMessage } from "@/constants/auth-messages";

export const metadata: Metadata = {
  title: AuthMessage.MyServiceRequestsTitle,
};

export default function MyServiceRequestsPage() {
  return (
    <>
      <PageHeader title={AuthMessage.MyServiceRequestsTitle} />
      <EmptyState
        description={AuthMessage.MyServiceRequestsPlaceholder}
        className="flex-1 rounded-lg border border-dashed border-border"
      />
    </>
  );
}
