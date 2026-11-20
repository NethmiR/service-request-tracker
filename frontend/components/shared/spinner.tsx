// Accessible loading indicator with a visually hidden label for screen readers.
// Inherits the current text colour so it fits inside buttons and on plain surfaces.

import { UiMessage } from "@/constants/ui-messages";
import { ComponentSize } from "@/enums/ui/component-size";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const sizeClassNames: Record<ComponentSize, string> = {
  [ComponentSize.Small]: "size-4 border-2",
  [ComponentSize.Medium]: "size-6 border-2",
  [ComponentSize.Large]: "size-10 border-4",
};

interface SpinnerProps {
  label?: string;
  size?: ComponentSize;
  className?: string;
}

export function Spinner({
  label = UiMessage.Loading,
  size = ComponentSize.Medium,
  className,
}: SpinnerProps) {
  return (
    <span role="status" className={mergeClassNames("inline-flex items-center", className)}>
      <span
        aria-hidden="true"
        className={mergeClassNames(
          "animate-spin rounded-full border-current border-r-transparent",
          sizeClassNames[size],
        )}
      />
      <span className="sr-only">{label}</span>
    </span>
  );
}
