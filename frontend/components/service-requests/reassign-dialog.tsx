// Dialog that lets the current assignee hand a service request to another technician or admin with a mandatory remark.
// Its form mounts only while open, so the assignee choices are loaded fresh each time and the form starts empty.
// The current assignee is left out of the choices, and the backend still rejects it.

"use client";

import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { WorkflowRemarkField } from "@/components/service-requests/workflow-remark-field";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { Dialog } from "@/components/shared/dialog";
import { FormField } from "@/components/shared/form-field";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { SectionLoader } from "@/components/shared/section-loader";
import { Select, type SelectOption } from "@/components/shared/select";
import {
  REASSIGN_SERVICE_REQUEST_FIELDS,
  ReassignServiceRequestField,
  type ReassignServiceRequestFieldName,
} from "@/constants/service-request-workflow-fields";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { LoadStatus } from "@/enums/ui/load-status";
import { useServiceRequestAssignees } from "@/hooks/service-requests/use-service-request-assignees";
import {
  emptyFormErrors,
  resolveFormErrors,
  type FormErrors,
} from "@/lib/forms/resolve-form-errors";
import { ApiError } from "@/lib/http/api-error";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";
import { isWorkflowConflict } from "@/lib/service-requests/is-workflow-conflict";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";
import type { UserReference } from "@/types/shared/user-reference";

interface ReassignFormProps {
  serviceRequest: ServiceRequestDetail;
  isSubmitting: boolean;
  onSubmittingChange: (isSubmitting: boolean) => void;
  onCancel: () => void;
  onReassigned: (detail: ServiceRequestDetail, assignee: UserReference) => void;
  onConflict: (error: ApiError) => void;
}

function ReassignForm({
  serviceRequest,
  isSubmitting,
  onSubmittingChange,
  onCancel,
  onReassigned,
  onConflict,
}: ReassignFormProps) {
  const { state, reload } = useServiceRequestAssignees();
  const [assigneeId, setAssigneeId] = useState("");
  const [remark, setRemark] = useState("");
  const [errors, setErrors] =
    useState<FormErrors<ReassignServiceRequestFieldName>>(emptyFormErrors);

  if (
    state.status === LoadStatus.Loading ||
    (state.status === LoadStatus.Failed && isHandledByRouteGuards(state.error))
  ) {
    return <SectionLoader />;
  }

  if (state.status === LoadStatus.Failed) {
    return (
      <LoadFailureAlert
        title={ServiceRequestWorkflowMessage.AssigneesLoadFailed}
        error={state.error}
        onRetry={reload}
      />
    );
  }

  const choices = state.resource.filter(
    (assignee) => assignee.id !== serviceRequest.assignee?.id,
  );
  const options: SelectOption<string>[] = choices.map((assignee) => ({
    value: String(assignee.id),
    label: assignee.name,
  }));
  const hasChoices = choices.length > 0;

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    const assignee = choices.find((choice) => String(choice.id) === assigneeId);

    if (assignee === undefined) {
      return;
    }

    onSubmittingChange(true);
    setErrors(emptyFormErrors());

    try {
      const detail = await serviceRequestService.reassign(serviceRequest.id, {
        assignee_id: assignee.id,
        remark,
      });
      onReassigned(detail, assignee);
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;

      if (apiError !== null && isWorkflowConflict(apiError)) {
        onConflict(apiError);
      } else if (!isHandledByRouteGuards(apiError)) {
        setErrors(resolveFormErrors(apiError, REASSIGN_SERVICE_REQUEST_FIELDS));
      }
    } finally {
      onSubmittingChange(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
      {hasChoices ? (
        <>
          <FormField
            label={ServiceRequestWorkflowMessage.AssigneeLabel}
            error={errors.fieldErrors[ReassignServiceRequestField.AssigneeId]}
            required
          >
            {(controlProps) => (
              <Select
                {...controlProps}
                name={ReassignServiceRequestField.AssigneeId}
                options={options}
                placeholder={ServiceRequestWorkflowMessage.AssigneePlaceholder}
                value={assigneeId}
                onChange={(event: ChangeEvent<HTMLSelectElement>) =>
                  setAssigneeId(event.target.value)
                }
                disabled={isSubmitting}
              />
            )}
          </FormField>
          <WorkflowRemarkField
            name={ReassignServiceRequestField.Remark}
            value={remark}
            onChange={setRemark}
            error={errors.fieldErrors[ReassignServiceRequestField.Remark]}
            required
            disabled={isSubmitting}
          />
        </>
      ) : (
        <Alert>{ServiceRequestWorkflowMessage.NoOtherAssignees}</Alert>
      )}
      <div className="flex justify-end gap-3">
        <Button variant={ButtonVariant.Ghost} onClick={onCancel} disabled={isSubmitting}>
          {UiMessage.Cancel}
        </Button>
        <Button type="submit" isLoading={isSubmitting} disabled={!hasChoices}>
          {ServiceRequestWorkflowMessage.ReassignSubmit}
        </Button>
      </div>
    </form>
  );
}

interface ReassignDialogProps {
  serviceRequest: ServiceRequestDetail;
  open: boolean;
  onClose: () => void;
  onReassigned: (detail: ServiceRequestDetail, assignee: UserReference) => void;
  onConflict: (error: ApiError) => void;
}

export function ReassignDialog({
  serviceRequest,
  open,
  onClose,
  onReassigned,
  onConflict,
}: ReassignDialogProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleClose() {
    if (!isSubmitting) {
      onClose();
    }
  }

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title={ServiceRequestWorkflowMessage.ReassignDialogTitle}
      description={ServiceRequestWorkflowMessage.ReassignDialogDescription}
      dismissible={!isSubmitting}
    >
      {open ? (
        <ReassignForm
          serviceRequest={serviceRequest}
          isSubmitting={isSubmitting}
          onSubmittingChange={setIsSubmitting}
          onCancel={handleClose}
          onReassigned={onReassigned}
          onConflict={onConflict}
        />
      ) : undefined}
    </Dialog>
  );
}
