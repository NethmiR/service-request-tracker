// Safe identity fields of the signed in user returned by the current user endpoint.
// Mirrors the backend CurrentUserResource in snake_case.

import type { UserRole } from "@/enums/user-role";

export interface CurrentUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  password_change_required: boolean;
}
