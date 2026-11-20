// Inline message with a colour tone, used for page level errors and notices.
// Danger alerts are announced immediately and the other tones politely.

import type { ReactNode } from "react";
import { AlertTone } from "@/enums/ui/alert-tone";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const toneClassNames: Record<AlertTone, string> = {
  [AlertTone.Info]: "border-primary/30 bg-primary/10",
  [AlertTone.Success]: "border-success/30 bg-success/10",
  [AlertTone.Warning]: "border-warning/30 bg-warning/10",
  [AlertTone.Danger]: "border-danger/30 bg-danger/10",
};

const titleClassNames: Record<AlertTone, string> = {
  [AlertTone.Info]: "text-primary-strong",
  [AlertTone.Success]: "text-success",
  [AlertTone.Warning]: "text-warning",
  [AlertTone.Danger]: "text-danger",
};

interface AlertProps {
  tone?: AlertTone;
  title?: string;
  action?: ReactNode;
  className?: string;
  children?: ReactNode;
}

export function Alert({ tone = AlertTone.Info, title, action, className, children }: AlertProps) {
  return (
    <div
      role={tone === AlertTone.Danger ? "alert" : "status"}
      className={mergeClassNames(
        "flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between",
        toneClassNames[tone],
        className,
      )}
    >
      <div className="flex flex-col gap-0.5">
        {title !== undefined && (
          <p className={mergeClassNames("text-sm font-semibold", titleClassNames[tone])}>{title}</p>
        )}
        {children !== undefined && <div className="text-sm text-foreground">{children}</div>}
      </div>
      {action !== undefined && <div className="shrink-0">{action}</div>}
    </div>
  );
}
