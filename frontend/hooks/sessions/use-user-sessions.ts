// Loads the active sessions of any user for an admin and ends one or all of them.
// Passes the admin session endpoints for the given user to the shared session list hook.

"use client";

import { useMemo } from "react";
import { useSessionList, type SessionList } from "@/hooks/sessions/use-session-list";
import { userService } from "@/services/users/user-service";
import type { SessionListSource } from "@/types/sessions/session-list-source";

export function useUserSessions(userId: number): SessionList {
  const source = useMemo<SessionListSource>(
    () => ({
      listSessions: () => userService.listUserSessions(userId),
      clearSession: (sessionIdentifier) => userService.clearUserSession(userId, sessionIdentifier),
      clearSessions: () => userService.clearUserSessions(userId),
    }),
    [userId],
  );

  return useSessionList(source);
}
