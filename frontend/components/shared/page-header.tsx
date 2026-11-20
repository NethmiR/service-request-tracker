// Page title with an optional description and a slot for page level actions.
// Every signed in page starts with it so headings look the same across features.

import type { ReactNode } from "react";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface PageHeaderProps {
  title: string;
  description?: string;
  actions?: ReactNode;
  className?: string;
}

export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
  return (
    <header
      className={mergeClassNames(
        "flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
    >
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl font-bold text-primary">{title}</h1>
        {description !== undefined && <p className="text-base text-foreground">{description}</p>}
      </div>
      {actions !== undefined && <div className="flex flex-wrap items-center gap-3">{actions}</div>}
    </header>
  );
}
