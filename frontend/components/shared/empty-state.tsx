// Placeholder shown when a list or table has nothing to display.
// Shows a title, a description and an optional action such as a create button.

import type { ReactNode } from "react";
import { UiMessage } from "@/constants/ui-messages";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface EmptyStateProps {
  title?: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}

export function EmptyState({
  title = UiMessage.EmptyTitle,
  description = UiMessage.EmptyDescription,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={mergeClassNames(
        "flex flex-col items-center justify-center gap-2 px-6 py-12 text-center",
        className,
      )}
    >
      <p className="text-base font-semibold text-foreground">{title}</p>
      <p className="max-w-md text-sm text-muted">{description}</p>
      {action !== undefined && <div className="mt-3">{action}</div>}
    </div>
  );
}
