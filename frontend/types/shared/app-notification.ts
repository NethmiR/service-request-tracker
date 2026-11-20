// Shape of a single app wide toast notification.
// Notifications live only in memory inside the notification context.

import type { NotificationType } from "@/enums/ui/notification-type";

export interface AppNotification {
  id: number;
  type: NotificationType;
  message: string;
}
