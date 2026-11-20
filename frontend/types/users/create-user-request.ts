// Body of the create user request sent by an admin.
// The backend generates the temporary password, so no password is sent.

import type { UserRole } from "@/enums/user-role";

export interface CreateUserRequest {
  name: string;
  email: string;
  role: UserRole;
}
