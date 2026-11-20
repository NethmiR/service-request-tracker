// One user as shown on the admin user management screens.
// Mirrors the backend UserResource in snake_case, with created_at as an ISO 8601 UTC timestamp.

import type { UserRole } from "@/enums/user-role";

export interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  password_change_required: boolean;
  created_at: string;
}
