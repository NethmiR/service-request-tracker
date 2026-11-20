// Wraps the application in its shared client side providers.
// The auth provider sits inside the notification provider because it shows notifications.

"use client";

import type { ReactNode } from "react";
import { ToastRegion } from "@/components/shared/toast-region";
import { AuthProvider } from "@/contexts/auth-context";
import { NotificationProvider } from "@/contexts/notification-context";

interface AppProvidersProps {
  children: ReactNode;
}

export function AppProviders({ children }: AppProvidersProps) {
  return (
    <NotificationProvider>
      <AuthProvider>{children}</AuthProvider>
      <ToastRegion />
    </NotificationProvider>
  );
}
