// Bordered card with a heading, an optional description and an optional actions slot.
// Groups related content inside a page, such as the account sections and later the detail sections.

import type { ReactNode } from "react";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface ContentSectionProps {
  title: string;
  description?: string;
  actions?: ReactNode;
  className?: string;
  children: ReactNode;
}

export function ContentSection({
  title,
  description,
  actions,
  className,
  children,
}: ContentSectionProps) {
  return (
    <section
      className={mergeClassNames(
        "flex flex-col gap-5 rounded-lg border border-border bg-surface p-5 sm:p-6",
        className,
      )}
    >
      <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-1">
          <h2 className="text-lg font-semibold text-primary">{title}</h2>
          {description !== undefined && <p className="text-sm text-muted">{description}</p>}
        </div>
        {actions !== undefined && (
          <div className="flex shrink-0 flex-wrap items-center gap-3">{actions}</div>
        )}
      </header>
      {children}
    </section>
  );
}
