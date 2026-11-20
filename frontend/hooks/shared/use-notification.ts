// Returns the notification context for showing and dismissing toasts.
// Throws a clear error when used outside NotificationProvider.

import { use } from "react";
import { DeveloperMessage } from "@/constants/developer-messages";
import {
  NotificationContext,
  type NotificationContextValue,
} from "@/contexts/notification-context";

export function useNotification(): NotificationContextValue {
  const context = use(NotificationContext);

  if (context === null) {
    throw new Error(DeveloperMessage.NotificationProviderMissing);
  }

  return context;
}
