// Small pill shaped label with a colour tone.
// Used for statuses, roles and tags.

import type { ReactNode } from "react";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const toneClassNames: Record<BadgeTone, string> = {
  [BadgeTone.Neutral]: "border-border bg-surface-muted text-foreground",
  [BadgeTone.Info]: "border-primary bg-primary/10 text-primary-strong",
  [BadgeTone.Success]: "border-success bg-success/10 text-success",
  [BadgeTone.Warning]: "border-warning bg-warning/10 text-warning",
  [BadgeTone.Danger]: "border-danger bg-danger/10 text-danger",
};

interface BadgeProps {
  tone?: BadgeTone;
  className?: string;
  children: ReactNode;
}

export function Badge({ tone = BadgeTone.Neutral, className, children }: BadgeProps) {
  return (
    <span
      className={mergeClassNames(
        "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap",
        toneClassNames[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
