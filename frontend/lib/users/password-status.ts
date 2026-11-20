// Maps a user's password change state to the badge label and tone shown on the user management screens.
// Shared by the users table and the user profile so both show the same status.

import { UserMessage } from "@/constants/user-messages";
import { BadgeTone } from "@/enums/ui/badge-tone";
import type { ManagedUser } from "@/types/users/managed-user";

export function passwordStatusLabel(user: ManagedUser): string {
  return user.password_change_required ? UserMessage.PasswordChangePending : UserMessage.PasswordActive;
}

export function passwordStatusTone(user: ManagedUser): BadgeTone {
  return user.password_change_required ? BadgeTone.Warning : BadgeTone.Success;
}
