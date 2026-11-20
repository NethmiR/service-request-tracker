// Loads the signed in user's active sessions and ends one or all other sessions.
// Passes the self service session endpoints to the shared session list hook.

"use client";

import { useSessionList, type SessionList } from "@/hooks/sessions/use-session-list";
import { sessionService } from "@/services/sessions/session-service";
import type { SessionListSource } from "@/types/sessions/session-list-source";

const OWN_SESSIONS_SOURCE: SessionListSource = {
  listSessions: () => sessionService.listOwnSessions(),
  clearSession: (sessionIdentifier) => sessionService.clearSession(sessionIdentifier),
  clearSessions: () => sessionService.clearOtherSessions(),
};

export function useOwnSessions(): SessionList {
  return useSessionList(OWN_SESSIONS_SOURCE);
}
