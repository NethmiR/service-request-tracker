// One active session of a user as returned by the session endpoints.
// Mirrors the backend SessionResource in snake_case, and id is the non reversible session identifier.

export interface UserSession {
  id: string;
  ip_address: string | null;
  user_agent: string | null;
  last_active_at: string;
  is_current: boolean;
}
