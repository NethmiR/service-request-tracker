// Group of checkboxes inside a fieldset with a legend, an optional hint and an optional error.
// Reports the selected values in the order of the options, so callers never sort them again.

"use client";

import { useId } from "react";
import { Checkbox } from "@/components/shared/checkbox";
import type { SelectOption } from "@/components/shared/select";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface CheckboxGroupProps<Value extends string> {
  legend: string;
  options: SelectOption<Value>[];
  values: readonly Value[];
  onChange: (values: Value[]) => void;
  hint?: string;
  error?: string;
  required?: boolean;
  disabled?: boolean;
  className?: string;
}

export function CheckboxGroup<Value extends string>({
  legend,
  options,
  values,
  onChange,
  hint,
  error,
  required = false,
  disabled = false,
  className,
}: CheckboxGroupProps<Value>) {
  const groupId = useId();
  const hintId = `${groupId}-hint`;
  const errorId = `${groupId}-error`;
  const hasError = error !== undefined && error !== "";

  const describedBy = [hint !== undefined ? hintId : null, hasError ? errorId : null]
    .filter((id) => id !== null)
    .join(" ");

  function handleToggle(value: Value, isChecked: boolean) {
    const nextValues = options
      .map((option) => option.value)
      .filter((optionValue) =>
        optionValue === value ? isChecked : values.includes(optionValue),
      );

    onChange(nextValues);
  }

  return (
    <fieldset
      disabled={disabled}
      aria-describedby={describedBy === "" ? undefined : describedBy}
      aria-invalid={hasError || undefined}
      aria-required={required || undefined}
      className={mergeClassNames("flex min-w-0 flex-col gap-1.5", className)}
    >
      <legend className="mb-1.5 text-sm font-medium text-foreground">{legend}</legend>
      <div
        className={mergeClassNames(
          "flex flex-wrap gap-x-6 gap-y-3 rounded-lg border border-border px-3 py-3",
          hasError && "border-danger",
        )}
      >
        {options.map((option) => (
          <Checkbox
            key={option.value}
            label={option.label}
            name={groupId}
            value={option.value}
            checked={values.includes(option.value)}
            disabled={disabled}
            onChange={(event) => handleToggle(option.value, event.target.checked)}
          />
        ))}
      </div>
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
    </fieldset>
  );
}
