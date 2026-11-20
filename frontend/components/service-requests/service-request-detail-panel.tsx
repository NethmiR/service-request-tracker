// Content of the request detail page with the request fields, the status history and the assignee history.
// A request that does not exist or that the user may not open shows the same not found message.

"use client";

import Link from "next/link";
import { AssigneeHistoryTimeline } from "@/components/service-requests/assignee-history-timeline";
import { ServiceRequestDetails } from "@/components/service-requests/service-request-details";
import { StatusHistoryTimeline } from "@/components/service-requests/status-history-timeline";
import { getButtonClassNames } from "@/components/shared/button";
import { EmptyState } from "@/components/shared/empty-state";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { SectionLoader } from "@/components/shared/section-loader";
import { AppRoute } from "@/constants/routes";
import { ServiceRequestMessage } from "@/constants/service-request-messages";
import { ErrorCode } from "@/enums/error-code";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { useServiceRequest } from "@/hooks/service-requests/use-service-request";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";

interface ServiceRequestDetailPanelProps {
  serviceRequestId: number;
}

export function ServiceRequestDetailPanel({ serviceRequestId }: ServiceRequestDetailPanelProps) {
  const { state, reload } = useServiceRequest(serviceRequestId);

  function renderContent() {
    const isRedirecting =
      state.status === LoadStatus.Failed && isHandledByRouteGuards(state.error);

    if (state.status === LoadStatus.Loading || isRedirecting) {
      return <SectionLoader />;
    }

    if (state.status === LoadStatus.Failed && state.error?.hasCode(ErrorCode.NotFound)) {
      return (
        <EmptyState
          title={ServiceRequestMessage.NotFoundTitle}
          description={ServiceRequestMessage.NotFoundDescription}
          action={
            <Link href={AppRoute.MyServiceRequests} className={getButtonClassNames()}>
              {ServiceRequestMessage.BackToMyRequests}
            </Link>
          }
          className="rounded-lg border border-border bg-surface"
        />
      );
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <LoadFailureAlert
          title={ServiceRequestMessage.RequestLoadFailed}
          error={state.error}
          onRetry={reload}
        />
      );
    }

    const serviceRequest = state.resource;

    return (
      <>
        <ServiceRequestDetails serviceRequest={serviceRequest} />
        <div className="grid gap-6 xl:grid-cols-2">
          <StatusHistoryTimeline entries={serviceRequest.status_history} />
          <AssigneeHistoryTimeline entries={serviceRequest.assignee_history} />
        </div>
      </>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <Link
        href={AppRoute.MyServiceRequests}
        className={getButtonClassNames(ButtonVariant.Ghost, ComponentSize.Small, "self-start")}
      >
        <span aria-hidden="true">&lsaquo;</span>
        {ServiceRequestMessage.BackToMyRequests}
      </Link>
      {renderContent()}
    </div>
  );
}
