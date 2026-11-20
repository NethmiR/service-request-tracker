// Single checkbox wrapped in its own clickable label.
// Phase 5 uses it to pick the tags of a service request.

import type { ComponentProps, ReactNode } from "react";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface CheckboxProps extends Omit<ComponentProps<"input">, "type"> {
  label: ReactNode;
}

export function Checkbox({ label, className, disabled, ...props }: CheckboxProps) {
  return (
    <label
      className={mergeClassNames(
        "inline-flex cursor-pointer items-center gap-2 text-sm text-foreground",
        disabled && "cursor-not-allowed text-muted",
        className,
      )}
    >
      <input
        type="checkbox"
        disabled={disabled}
        className="size-4 cursor-pointer rounded border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed"
        {...props}
      />
      <span>{label}</span>
    </label>
  );
}
