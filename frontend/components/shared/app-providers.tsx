// Wraps the application in its shared client side providers.
// Phase 2 adds the auth provider here.

"use client";

import type { ReactNode } from "react";
import { ToastRegion } from "@/components/shared/toast-region";
import { NotificationProvider } from "@/contexts/notification-context";

interface AppProvidersProps {
  children: ReactNode;
}

export function AppProviders({ children }: AppProvidersProps) {
  return (
    <NotificationProvider>
      {children}
      <ToastRegion />
    </NotificationProvider>
  );
}
