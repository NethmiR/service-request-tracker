// Data source that the shared session list hook reads from and acts on.
// The account page passes the signed in user's endpoints and the user detail page passes the admin endpoints.

import type { UserSession } from "@/types/sessions/user-session";

export interface SessionListSource {
  listSessions: () => Promise<UserSession[]>;
  clearSession: (sessionIdentifier: string) => Promise<void>;
  clearSessions: () => Promise<void>;
}
