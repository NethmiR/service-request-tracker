// Form that lets the current assignee move a service request to one of the statuses the backend allows.
// The select starts on the current status, and the remark is optional with a blank remark sent as null.
// A conflict or forbidden response is handed to the parent, which reloads the request.

"use client";

import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { WorkflowRemarkField } from "@/components/service-requests/workflow-remark-field";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { FormField } from "@/components/shared/form-field";
import { Select } from "@/components/shared/select";
import {
  CHANGE_SERVICE_REQUEST_STATUS_FIELDS,
  ChangeServiceRequestStatusField,
  type ChangeServiceRequestStatusFieldName,
} from "@/constants/service-request-workflow-fields";
import {
  ServiceRequestWorkflowMessage,
  statusOptions,
} from "@/constants/service-request-workflow-messages";
import type { ServiceRequestStatus } from "@/enums/service-request-status";
import { AlertTone } from "@/enums/ui/alert-tone";
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

interface ChangeStatusFormProps {
  serviceRequest: ServiceRequestDetail;
  onChanged: (detail: ServiceRequestDetail) => void;
  onConflict: (error: ApiError) => void;
}

export function ChangeStatusForm({ serviceRequest, onChanged, onConflict }: ChangeStatusFormProps) {
  const currentStatus = serviceRequest.status;
  const allowedStatuses = serviceRequest.available_actions.allowed_statuses;
  const [status, setStatus] = useState<ServiceRequestStatus>(currentStatus);
  const isUnchanged = status === currentStatus;
  const [remark, setRemark] = useState("");
  const [errors, setErrors] =
    useState<FormErrors<ChangeServiceRequestStatusFieldName>>(emptyFormErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function resetForm() {
    setStatus(currentStatus);
    setRemark("");
    setErrors(emptyFormErrors());
  }

  function handleStatusChange(event: ChangeEvent<HTMLSelectElement>) {
    setStatus(allowedStatuses.find((allowed) => allowed === event.target.value) ?? currentStatus);
  }

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    if (isUnchanged) {
      return;
    }

    const trimmedRemark = remark.trim();
    setIsSubmitting(true);
    setErrors(emptyFormErrors());

    try {
      const detail = await serviceRequestService.changeStatus(serviceRequest.id, {
        status,
        remark: trimmedRemark === "" ? null : trimmedRemark,
      });
      resetForm();
      onChanged(detail);
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;

      if (apiError !== null && isWorkflowConflict(apiError)) {
        resetForm();
        onConflict(apiError);
      } else if (!isHandledByRouteGuards(apiError)) {
        setErrors(resolveFormErrors(apiError, CHANGE_SERVICE_REQUEST_STATUS_FIELDS));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
      <FormField
        label={ServiceRequestWorkflowMessage.StatusLabel}
        error={errors.fieldErrors[ChangeServiceRequestStatusField.Status]}
        required
      >
        {(controlProps) => (
          <Select
            {...controlProps}
            name={ChangeServiceRequestStatusField.Status}
            options={statusOptions(currentStatus, allowedStatuses)}
            value={status}
            onChange={handleStatusChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <WorkflowRemarkField
        name={ChangeServiceRequestStatusField.Remark}
        value={remark}
        onChange={setRemark}
        error={errors.fieldErrors[ChangeServiceRequestStatusField.Remark]}
        disabled={isSubmitting}
      />
      <div className="flex justify-end">
        <Button type="submit" isLoading={isSubmitting} disabled={isUnchanged}>
          {ServiceRequestWorkflowMessage.StatusSubmit}
        </Button>
      </div>
    </form>
  );
}
