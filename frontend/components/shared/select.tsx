// Native select with typed options of value and label.
// An optional placeholder adds an empty first option that cannot be chosen again.

import type { ComponentProps } from "react";
import { FORM_CONTROL_CLASS_NAMES } from "@/components/shared/form-control-class-names";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

export interface SelectOption<Value extends string> {
  value: Value;
  label: string;
}

interface SelectProps<Value extends string> extends Omit<ComponentProps<"select">, "children"> {
  options: SelectOption<Value>[];
  placeholder?: string;
  hasError?: boolean;
}

export function Select<Value extends string>({
  options,
  placeholder,
  hasError = false,
  className,
  "aria-invalid": ariaInvalid,
  ...props
}: SelectProps<Value>) {
  return (
    <div className="relative">
      <select
        aria-invalid={hasError || ariaInvalid || undefined}
        className={mergeClassNames(
          FORM_CONTROL_CLASS_NAMES,
          "h-10 cursor-pointer appearance-none pr-10",
          className,
        )}
        {...props}
      >
        {placeholder !== undefined && (
          <option value="" disabled>
            {placeholder}
          </option>
        )}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <svg
        aria-hidden="true"
        viewBox="0 0 16 16"
        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted"
      >
        <path
          d="M4 6l4 4 4-4"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    </div>
  );
}
