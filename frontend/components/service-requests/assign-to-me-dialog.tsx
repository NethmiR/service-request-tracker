// Dialog that assigns an open service request to the signed in user with a mandatory remark.
// Open while a request is given, and shared by the dashboard and the request detail page.
// A conflict or forbidden response is handed to the parent, which closes the dialog and reloads the request.

"use client";

import { useState } from "react";
import type { SubmitEvent } from "react";
import { WorkflowRemarkField } from "@/components/service-requests/workflow-remark-field";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { Dialog } from "@/components/shared/dialog";
import { formatRequestReference } from "@/constants/service-request-messages";
import {
  ASSIGN_SERVICE_REQUEST_FIELDS,
  AssignServiceRequestField,
  type AssignServiceRequestFieldName,
} from "@/constants/service-request-workflow-fields";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
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
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";

interface AssignToMeDialogProps {
  serviceRequest: ServiceRequestSummary | null;
  onClose: () => void;
  onAssigned: (detail: ServiceRequestDetail) => void;
  onConflict: (error: ApiError) => void;
}

export function AssignToMeDialog({
  serviceRequest,
  onClose,
  onAssigned,
  onConflict,
}: AssignToMeDialogProps) {
  const [remark, setRemark] = useState("");
  const [errors, setErrors] = useState<FormErrors<AssignServiceRequestFieldName>>(emptyFormErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function resetForm() {
    setRemark("");
    setErrors(emptyFormErrors());
  }

  function handleClose() {
    if (isSubmitting) {
      return;
    }

    resetForm();
    onClose();
  }

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    if (serviceRequest === null) {
      return;
    }

    setIsSubmitting(true);
    setErrors(emptyFormErrors());

    try {
      const detail = await serviceRequestService.assignToSelf(serviceRequest.id, { remark });
      resetForm();
      onAssigned(detail);
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;

      if (apiError !== null && isWorkflowConflict(apiError)) {
        resetForm();
        onConflict(apiError);
      } else if (!isHandledByRouteGuards(apiError)) {
        setErrors(resolveFormErrors(apiError, ASSIGN_SERVICE_REQUEST_FIELDS));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Dialog
      open={serviceRequest !== null}
      onClose={handleClose}
      title={ServiceRequestWorkflowMessage.AssignDialogTitle}
      description={ServiceRequestWorkflowMessage.AssignDialogDescription}
      dismissible={!isSubmitting}
    >
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        {serviceRequest !== null && (
          <div className="flex flex-col gap-0.5 rounded-lg border border-border-subtle bg-surface-muted px-4 py-3">
            <p className="text-sm font-medium break-words text-foreground">{serviceRequest.title}</p>
            <p className="text-xs text-muted">{formatRequestReference(serviceRequest.id)}</p>
          </div>
        )}
        {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
        <WorkflowRemarkField
          name={AssignServiceRequestField.Remark}
          value={remark}
          onChange={setRemark}
          error={errors.fieldErrors[AssignServiceRequestField.Remark]}
          required
          disabled={isSubmitting}
        />
        <div className="flex justify-end gap-3">
          <Button variant={ButtonVariant.Ghost} onClick={handleClose} disabled={isSubmitting}>
            {UiMessage.Cancel}
          </Button>
          <Button type="submit" isLoading={isSubmitting}>
            {ServiceRequestWorkflowMessage.AssignSubmit}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
