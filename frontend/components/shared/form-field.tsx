// Wraps a form control with its label, optional hint and error message.
// Generates the ids that link the label, hint and error to the control for screen readers.

import { useId } from "react";
import type { ReactNode } from "react";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";
import type { FormControlProps } from "@/types/shared/form-control-props";

interface FormFieldProps {
  label: string;
  hint?: string;
  error?: string;
  required?: boolean;
  className?: string;
  children: (controlProps: FormControlProps) => ReactNode;
}

export function FormField({
  label,
  hint,
  error,
  required = false,
  className,
  children,
}: FormFieldProps) {
  const controlId = useId();
  const hintId = `${controlId}-hint`;
  const errorId = `${controlId}-error`;
  const hasError = error !== undefined && error !== "";

  const describedBy = [hint !== undefined ? hintId : null, hasError ? errorId : null]
    .filter((id) => id !== null)
    .join(" ");

  const controlProps: FormControlProps = {
    id: controlId,
    required: required || undefined,
    "aria-describedby": describedBy === "" ? undefined : describedBy,
    "aria-invalid": hasError ? true : undefined,
  };

  return (
    <div className={mergeClassNames("flex flex-col gap-1.5", className)}>
      <label htmlFor={controlId} className="text-sm font-medium text-foreground">
        {label}
      </label>
      {children(controlProps)}
      {hint !== undefined && (
        <p id={hintId} className="text-xs text-muted">
          {hint}
        </p>
      )}
      {hasError && (
        <p id={errorId} className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
