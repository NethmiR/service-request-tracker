// Section of the request detail page with the assign to me and reassign actions and the status change form.
// Shows only the controls the backend lists in available_actions and renders nothing when no action is available.
// Owns both dialogs, shows the success notifications and hands updated requests and conflicts to the parent.

"use client";

import { useState } from "react";
import { AssignToMeDialog } from "@/components/service-requests/assign-to-me-dialog";
import { ChangeStatusForm } from "@/components/service-requests/change-status-form";
import { ReassignDialog } from "@/components/service-requests/reassign-dialog";
import { Button } from "@/components/shared/button";
import { ContentSection } from "@/components/shared/content-section";
import {
  ServiceRequestWorkflowMessage,
  formatReassignedNotification,
  formatStatusChangedNotification,
} from "@/constants/service-request-workflow-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import type { ApiError } from "@/lib/http/api-error";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";
import type { UserReference } from "@/types/shared/user-reference";

interface ServiceRequestWorkflowSectionProps {
  serviceRequest: ServiceRequestDetail;
  onUpdated: (detail: ServiceRequestDetail) => void;
  onConflict: (error: ApiError) => void;
}

export function ServiceRequestWorkflowSection({
  serviceRequest,
  onUpdated,
  onConflict,
}: ServiceRequestWorkflowSectionProps) {
  const { notify } = useNotification();
  const [isAssignOpen, setIsAssignOpen] = useState(false);
  const [isReassignOpen, setIsReassignOpen] = useState(false);
  const { can_assign_to_self, can_reassign, allowed_statuses } = serviceRequest.available_actions;

  if (!can_assign_to_self && !can_reassign && allowed_statuses.length === 0) {
    return null;
  }

  function closeDialogs() {
    setIsAssignOpen(false);
    setIsReassignOpen(false);
  }

  function handleAssigned(detail: ServiceRequestDetail) {
    closeDialogs();
    notify(NotificationType.Success, ServiceRequestWorkflowMessage.RequestAssigned);
    onUpdated(detail);
  }

  function handleReassigned(detail: ServiceRequestDetail, assignee: UserReference) {
    closeDialogs();
    notify(NotificationType.Success, formatReassignedNotification(assignee.name));
    onUpdated(detail);
  }

  function handleStatusChanged(detail: ServiceRequestDetail) {
    notify(NotificationType.Success, formatStatusChangedNotification(detail.status));
    onUpdated(detail);
  }

  function handleConflict(error: ApiError) {
    closeDialogs();
    onConflict(error);
  }

  return (
    <ContentSection
      title={ServiceRequestWorkflowMessage.WorkSectionTitle}
      description={ServiceRequestWorkflowMessage.WorkSectionDescription}
      actions={
        can_assign_to_self || can_reassign ? (
          <>
            {can_assign_to_self && (
              <Button onClick={() => setIsAssignOpen(true)}>
                {ServiceRequestWorkflowMessage.AssignToMe}
              </Button>
            )}
            {can_reassign && (
              <Button variant={ButtonVariant.Secondary} onClick={() => setIsReassignOpen(true)}>
                {ServiceRequestWorkflowMessage.Reassign}
              </Button>
            )}
          </>
        ) : undefined
      }
    >
      {allowed_statuses.length > 0 && (
        <ChangeStatusForm
          key={serviceRequest.status}
          serviceRequest={serviceRequest}
          onChanged={handleStatusChanged}
          onConflict={handleConflict}
        />
      )}
      <AssignToMeDialog
        serviceRequest={isAssignOpen ? serviceRequest : null}
        onClose={() => setIsAssignOpen(false)}
        onAssigned={handleAssigned}
        onConflict={handleConflict}
      />
      <ReassignDialog
        serviceRequest={serviceRequest}
        open={isReassignOpen}
        onClose={() => setIsReassignOpen(false)}
        onReassigned={handleReassigned}
        onConflict={handleConflict}
      />
    </ContentSection>
  );
}
