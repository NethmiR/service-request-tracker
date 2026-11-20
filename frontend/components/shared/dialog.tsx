// Modal dialog built on the native dialog element and opened with showModal.
// Escape closes it through onClose and the browser returns focus to the opener.

"use client";

import { useEffect, useId, useRef } from "react";
import type { ReactNode } from "react";
import { UiMessage } from "@/constants/ui-messages";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  footer?: ReactNode;
  className?: string;
  children?: ReactNode;
}

export function Dialog({
  open,
  onClose,
  title,
  description,
  footer,
  className,
  children,
}: DialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const descriptionId = useId();

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.showModal();
    }

    if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  function handleClose() {
    if (open) {
      onClose();
    }
  }

  return (
    <dialog
      ref={dialogRef}
      onClose={handleClose}
      aria-labelledby={titleId}
      aria-describedby={description !== undefined ? descriptionId : undefined}
      className={mergeClassNames(
        "m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border border-border bg-surface p-0 text-foreground shadow-xl",
        "backdrop:bg-black/40",
        className,
      )}
    >
      <div className="flex items-start justify-between gap-4 border-b border-border-subtle px-6 py-4">
        <div className="flex flex-col gap-1">
          <h2 id={titleId} className="text-lg font-semibold text-primary">
            {title}
          </h2>
          {description !== undefined && (
            <p id={descriptionId} className="text-sm text-muted">
              {description}
            </p>
          )}
        </div>
        <button
          type="button"
          onClick={onClose}
          aria-label={UiMessage.Close}
          className="-mr-2 flex size-8 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-primary"
        >
          <svg aria-hidden="true" viewBox="0 0 16 16" className="size-4">
            <path
              d="M4 4l8 8M12 4l-8 8"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
            />
          </svg>
        </button>
      </div>
      {children !== undefined && <div className="px-6 py-5">{children}</div>}
      {footer !== undefined && (
        <div className="flex justify-end gap-3 border-t border-border-subtle px-6 py-4">
          {footer}
        </div>
      )}
    </dialog>
  );
}
