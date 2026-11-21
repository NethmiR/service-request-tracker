// Content of the request detail page with the request fields, the workflow controls and both history timelines.
// A request that does not exist or that the user may not open shows the same not found message.
// A workflow change shows the returned request, and a conflict shows its message and reloads the latest request.

"use client";

import Link from "next/link";
import { AssigneeHistoryTimeline } from "@/components/service-requests/assignee-history-timeline";
import { ServiceRequestDetails } from "@/components/service-requests/service-request-details";
import { ServiceRequestWorkflowSection } from "@/components/service-requests/service-request-workflow-section";
import { StatusHistoryTimeline } from "@/components/service-requests/status-history-timeline";
import { getButtonClassNames } from "@/components/shared/button";
import { EmptyState } from "@/components/shared/empty-state";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { SectionLoader } from "@/components/shared/section-loader";
import { ServiceRequestMessage } from "@/constants/service-request-messages";
import { ErrorCode } from "@/enums/error-code";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useAuth } from "@/hooks/auth/use-auth";
import { useServiceRequest } from "@/hooks/service-requests/use-service-request";
import { useNotification } from "@/hooks/shared/use-notification";
import type { ApiError } from "@/lib/http/api-error";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";
import { messageForError } from "@/lib/http/message-for-error";
import { serviceRequestBackLink } from "@/lib/service-requests/service-request-back-link";

interface ServiceRequestDetailPanelProps {
  serviceRequestId: number;
}

export function ServiceRequestDetailPanel({ serviceRequestId }: ServiceRequestDetailPanelProps) {
  const { user } = useAuth();
  const { notify } = useNotification();
  const { state, reload, replace } = useServiceRequest(serviceRequestId);

  if (user === null) {
    return <SectionLoader />;
  }

  const backLink = serviceRequestBackLink(user.role);

  function handleConflict(error: ApiError) {
    notify(NotificationType.Error, messageForError(error));
    void reload();
  }

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
            <Link href={backLink.route} className={getButtonClassNames()}>
              {backLink.label}
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
        <ServiceRequestWorkflowSection
          serviceRequest={serviceRequest}
          onUpdated={replace}
          onConflict={handleConflict}
        />
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
        href={backLink.route}
        className={getButtonClassNames(ButtonVariant.Ghost, ComponentSize.Small, "self-start")}
      >
        <span aria-hidden="true">&lsaquo;</span>
        {backLink.label}
      </Link>
      {renderContent()}
    </div>
  );
}
