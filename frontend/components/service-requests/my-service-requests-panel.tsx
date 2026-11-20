// Content of the My requests page with the paginated list of the signed in user's requests.
// Offers a link to raise a new request and keeps the previous page visible while the next one loads.

"use client";

import Link from "next/link";
import { MyServiceRequestsTable } from "@/components/service-requests/my-service-requests-table";
import { getButtonClassNames } from "@/components/shared/button";
import { ContentSection } from "@/components/shared/content-section";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { Pagination } from "@/components/shared/pagination";
import { SectionLoader } from "@/components/shared/section-loader";
import { AppRoute } from "@/constants/routes";
import { ServiceRequestMessage } from "@/constants/service-request-messages";
import { LoadStatus } from "@/enums/ui/load-status";
import { useMyServiceRequests } from "@/hooks/service-requests/use-my-service-requests";

export function MyServiceRequestsPanel() {
  const { state, goToPage, isChangingPage, reload } = useMyServiceRequests();

  function renderContent() {
    if (state.status === LoadStatus.Loading) {
      return <SectionLoader />;
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <LoadFailureAlert
          title={ServiceRequestMessage.RequestsLoadFailed}
          error={state.error}
          onRetry={reload}
        />
      );
    }

    return (
      <div className="flex flex-col gap-4" aria-busy={isChangingPage || undefined}>
        <MyServiceRequestsTable serviceRequests={state.page.data} />
        <Pagination meta={state.page.meta} onPageChange={goToPage} disabled={isChangingPage} />
      </div>
    );
  }

  return (
    <ContentSection
      title={ServiceRequestMessage.ListTitle}
      description={ServiceRequestMessage.ListDescription}
      actions={
        <Link href={AppRoute.NewServiceRequest} className={getButtonClassNames()}>
          {ServiceRequestMessage.NewRequest}
        </Link>
      }
    >
      {renderContent()}
    </ContentSection>
  );
}
