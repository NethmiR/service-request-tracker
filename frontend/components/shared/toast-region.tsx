// Renders the active app wide notifications as toasts in a polite live region.
// Each toast can be dismissed early and otherwise closes on its own.

"use client";

import { UiMessage } from "@/constants/ui-messages";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const toneClassNames: Record<NotificationType, string> = {
  [NotificationType.Success]: "border-l-success",
  [NotificationType.Error]: "border-l-danger",
  [NotificationType.Info]: "border-l-primary",
  [NotificationType.Warning]: "border-l-warning",
};

export function ToastRegion() {
  const { notifications, dismiss } = useNotification();

  return (
    <section
      aria-label={UiMessage.NotificationsRegion}
      aria-live="polite"
      className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-full max-w-sm flex-col gap-3"
    >
      {notifications.map((notification) => (
        <div
          key={notification.id}
          role={notification.type === NotificationType.Error ? "alert" : "status"}
          className={mergeClassNames(
            "pointer-events-auto flex items-start gap-3 rounded-lg border border-l-4 border-border bg-surface px-4 py-3 text-sm text-foreground shadow-lg",
            toneClassNames[notification.type],
          )}
        >
          <p className="flex-1">{notification.message}</p>
          <button
            type="button"
            onClick={() => dismiss(notification.id)}
            aria-label={UiMessage.DismissNotification}
            className="cursor-pointer rounded px-1 text-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      ))}
    </section>
  );
}
