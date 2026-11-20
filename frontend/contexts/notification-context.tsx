// Provides app wide toast notifications through notify and dismiss.
// Notifications close automatically after a shared duration.

"use client";

import { createContext, useCallback, useEffect, useMemo, useRef, useState } from "react";
import type { ReactNode } from "react";
import { NOTIFICATION_DURATION_MILLISECONDS } from "@/constants/notifications";
import type { NotificationType } from "@/enums/ui/notification-type";
import type { AppNotification } from "@/types/shared/app-notification";

export interface NotificationContextValue {
  notifications: AppNotification[];
  notify: (type: NotificationType, message: string) => void;
  dismiss: (id: number) => void;
}

export const NotificationContext = createContext<NotificationContextValue | null>(null);

interface NotificationProviderProps {
  children: ReactNode;
}

export function NotificationProvider({ children }: NotificationProviderProps) {
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const nextIdRef = useRef(1);
  const timersRef = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((id: number) => {
    const timer = timersRef.current.get(id);

    if (timer !== undefined) {
      clearTimeout(timer);
      timersRef.current.delete(id);
    }

    setNotifications((current) => current.filter((notification) => notification.id !== id));
  }, []);

  const notify = useCallback(
    (type: NotificationType, message: string) => {
      const id = nextIdRef.current;
      nextIdRef.current += 1;

      setNotifications((current) => [...current, { id, type, message }]);
      timersRef.current.set(
        id,
        setTimeout(() => dismiss(id), NOTIFICATION_DURATION_MILLISECONDS),
      );
    },
    [dismiss],
  );

  useEffect(() => {
    const timers = timersRef.current;

    return () => {
      timers.forEach((timer) => clearTimeout(timer));
      timers.clear();
    };
  }, []);

  const value = useMemo(
    () => ({ notifications, notify, dismiss }),
    [notifications, notify, dismiss],
  );

  return <NotificationContext value={value}>{children}</NotificationContext>;
}
