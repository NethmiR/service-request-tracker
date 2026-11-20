// Ordered list of dated entries, each with a heading, a meta line and an optional body.
// Shows the shared empty state when there are no entries, and keeps the order its caller passes.

import type { Key, ReactNode } from "react";
import { EmptyState } from "@/components/shared/empty-state";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

export interface TimelineItem {
  key: Key;
  heading: ReactNode;
  meta?: ReactNode;
  dateTime: string;
  formattedDateTime: string;
  body?: ReactNode;
}

interface TimelineProps {
  items: TimelineItem[];
  bodyLabel?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  className?: string;
}

export function Timeline({
  items,
  bodyLabel,
  emptyTitle,
  emptyDescription,
  className,
}: TimelineProps) {
  if (items.length === 0) {
    return (
      <EmptyState
        title={emptyTitle}
        description={emptyDescription}
        className="rounded-lg border border-dashed border-border py-8"
      />
    );
  }

  return (
    <ol className={mergeClassNames("flex flex-col", className)}>
      {items.map((item) => (
        <li key={item.key} className="group flex gap-4 pb-6 last:pb-0">
          <div aria-hidden="true" className="flex flex-col items-center">
            <span className="mt-1.5 size-3 shrink-0 rounded-full border-2 border-primary bg-surface" />
            <span className="mt-1 w-px flex-1 bg-border group-last:hidden" />
          </div>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <p className="text-sm font-semibold text-foreground">{item.heading}</p>
            <p className="flex flex-wrap items-center gap-x-2 text-xs text-muted">
              <time dateTime={item.dateTime}>{item.formattedDateTime}</time>
              {item.meta !== undefined && (
                <>
                  <span aria-hidden="true">&middot;</span>
                  <span>{item.meta}</span>
                </>
              )}
            </p>
            {item.body !== undefined && (
              <div className="mt-1 rounded-lg bg-surface-muted px-3 py-2 text-sm whitespace-pre-wrap break-words text-foreground">
                {bodyLabel !== undefined && <span className="sr-only">{bodyLabel}</span>}
                {item.body}
              </div>
            )}
          </div>
        </li>
      ))}
    </ol>
  );
}
