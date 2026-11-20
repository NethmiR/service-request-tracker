// Single line text input that forwards every native input prop.
// Sets aria-invalid when an error is present so the error style and screen readers react.

import type { ComponentProps } from "react";
import { FORM_CONTROL_CLASS_NAMES } from "@/components/shared/form-control-class-names";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface InputProps extends ComponentProps<"input"> {
  hasError?: boolean;
}

export function Input({
  hasError = false,
  type = "text",
  className,
  "aria-invalid": ariaInvalid,
  ...props
}: InputProps) {
  return (
    <input
      type={type}
      aria-invalid={hasError || ariaInvalid || undefined}
      className={mergeClassNames(FORM_CONTROL_CLASS_NAMES, "h-10", className)}
      {...props}
    />
  );
}
