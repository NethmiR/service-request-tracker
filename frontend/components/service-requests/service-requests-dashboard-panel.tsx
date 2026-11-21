// Content of the request dashboard with the paginated list of every service request and the assign to me dialog.
// After an assignment or a conflict the user stays on the same page, which reloads to show the latest state.

"use client";

import { useState } from "react";
import { AssignToMeDialog } from "@/components/service-requests/assign-to-me-dialog";
import { ServiceRequestFilterBar } from "@/components/service-requests/service-request-filter-bar";
import { ServiceRequestsTable } from "@/components/service-requests/service-requests-table";
import { ContentSection } from "@/components/shared/content-section";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { Pagination } from "@/components/shared/pagination";
import { SectionLoader } from "@/components/shared/section-loader";
import { NO_SERVICE_REQUEST_FILTERS } from "@/constants/service-request-filter-fields";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useServiceRequestAssignees } from "@/hooks/service-requests/use-service-request-assignees";
import { useServiceRequests } from "@/hooks/service-requests/use-service-requests";
import { useNotification } from "@/hooks/shared/use-notification";
import type { ApiError } from "@/lib/http/api-error";
import { messageForError } from "@/lib/http/message-for-error";
import { hasActiveFilters } from "@/lib/service-requests/service-request-filters";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";

export function ServiceRequestsDashboardPanel() {
  const { notify } = useNotification();
  const [filters, setFilters] = useState<ServiceRequestFilters>(NO_SERVICE_REQUEST_FILTERS);
  const { state, goToPage, isChangingPage, reload } = useServiceRequests(filters);
  const assignees = useServiceRequestAssignees();
  const [requestToAssign, setRequestToAssign] = useState<ServiceRequestSummary | null>(null);

  function handleAssigned() {
    setRequestToAssign(null);
    notify(NotificationType.Success, ServiceRequestWorkflowMessage.RequestAssigned);
    void reload();
  }

  function handleConflict(error: ApiError) {
    setRequestToAssign(null);
    notify(NotificationType.Error, messageForError(error));
    void reload();
  }

  function renderContent() {
    if (state.status === LoadStatus.Loading) {
      return <SectionLoader />;
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <LoadFailureAlert
          title={ServiceRequestWorkflowMessage.RequestsLoadFailed}
          error={state.error}
          onRetry={reload}
        />
      );
    }

    return (
      <div className="flex flex-col gap-4" aria-busy={isChangingPage || undefined}>
        <ServiceRequestsTable
          serviceRequests={state.page.data}
          isFiltered={hasActiveFilters(filters)}
          onAssign={setRequestToAssign}
        />
        <Pagination meta={state.page.meta} onPageChange={goToPage} disabled={isChangingPage} />
      </div>
    );
  }

  return (
    <ContentSection
      title={ServiceRequestWorkflowMessage.ListTitle}
      description={ServiceRequestWorkflowMessage.ListDescription}
    >
      <ServiceRequestFilterBar filters={filters} assignees={assignees} onChange={setFilters} />
      {renderContent()}
      <AssignToMeDialog
        serviceRequest={requestToAssign}
        onClose={() => setRequestToAssign(null)}
        onAssigned={handleAssigned}
        onConflict={handleConflict}
      />
    </ContentSection>
  );
}
