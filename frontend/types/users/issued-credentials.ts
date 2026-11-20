// Response of creating a user or resetting a password, mirroring the backend IssuedCredentialsResource.
// The temporary password is null when it was delivered elsewhere, in which case no dialog is shown.

import type { ManagedUser } from "@/types/users/managed-user";

export interface IssuedCredentials {
  user: ManagedUser;
  temporary_password: string | null;
}
