// Remark field shared by the assign to me dialog, the reassign dialog and the status change form.
// Shows the required or optional hint and keeps the remark within the limit the backend validates again.

import type { ChangeEvent } from "react";
import { FormField } from "@/components/shared/form-field";
import { Textarea } from "@/components/shared/textarea";
import { SERVICE_REQUEST_REMARK_MAX_LENGTH } from "@/constants/service-request-limits";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";

const REMARK_ROWS = 3;

interface WorkflowRemarkFieldProps {
  name: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
  disabled?: boolean;
}

export function WorkflowRemarkField({
  name,
  value,
  onChange,
  error,
  required = false,
  disabled = false,
}: WorkflowRemarkFieldProps) {
  return (
    <FormField
      label={ServiceRequestWorkflowMessage.RemarkLabel}
      hint={
        required
          ? ServiceRequestWorkflowMessage.RemarkRequiredHint
          : ServiceRequestWorkflowMessage.RemarkOptionalHint
      }
      error={error}
      required={required}
    >
      {(controlProps) => (
        <Textarea
          {...controlProps}
          name={name}
          value={value}
          rows={REMARK_ROWS}
          maxLength={SERVICE_REQUEST_REMARK_MAX_LENGTH}
          placeholder={ServiceRequestWorkflowMessage.RemarkPlaceholder}
          onChange={(event: ChangeEvent<HTMLTextAreaElement>) => onChange(event.target.value)}
          disabled={disabled}
        />
      )}
    </FormField>
  );
}
