// Card that shows a label and a single number.
// Used for the count boxes on the overview page.

import { DISPLAY_LOCALE } from "@/constants/application";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const numberFormatter = new Intl.NumberFormat(DISPLAY_LOCALE);

interface StatCardProps {
  label: string;
  value: number;
  description?: string;
  className?: string;
}

export function StatCard({ label, value, description, className }: StatCardProps) {
  return (
    <div
      className={mergeClassNames(
        "flex flex-col gap-1 rounded-lg border border-border bg-surface px-5 py-4",
        className,
      )}
    >
      <p className="text-sm font-medium text-muted">{label}</p>
      <p className="text-3xl font-bold text-primary tabular-nums">{numberFormatter.format(value)}</p>
      {description !== undefined && <p className="text-xs text-muted">{description}</p>}
    </div>
  );
}
