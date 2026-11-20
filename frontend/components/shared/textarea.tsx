// Multi line text input that forwards every native textarea prop.
// Shares the error handling and styling of the single line input.

import type { ComponentProps } from "react";
import { FORM_CONTROL_CLASS_NAMES } from "@/components/shared/form-control-class-names";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface TextareaProps extends ComponentProps<"textarea"> {
  hasError?: boolean;
}

export function Textarea({
  hasError = false,
  rows = 4,
  className,
  "aria-invalid": ariaInvalid,
  ...props
}: TextareaProps) {
  return (
    <textarea
      rows={rows}
      aria-invalid={hasError || ariaInvalid || undefined}
      className={mergeClassNames(FORM_CONTROL_CLASS_NAMES, "resize-y py-2 leading-relaxed", className)}
      {...props}
    />
  );
}
