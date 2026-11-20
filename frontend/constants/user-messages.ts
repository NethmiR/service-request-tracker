// Text for the user management pages, the create user dialog, the reset action and the temporary password dialog.
// Also builds the role options of the create user form from the shared role list and labels.

import type { SelectOption } from "@/components/shared/select";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { ALL_ROLES } from "@/constants/role-groups";
import type { UserRole } from "@/enums/user-role";

export const UserMessage = {
  PageTitle: "Users",
  PageDescription: "Create accounts, reset passwords and manage where each user is signed in.",
  ListTitle: "All users",
  ListDescription: "Newest accounts first. Open a user to reset their password or end their sessions.",
  TableCaption: "Users",
  NameColumn: "Name",
  EmailColumn: "Email",
  RoleColumn: "Role",
  PasswordColumn: "Password",
  CreatedColumn: "Created",
  EmptyTitle: "No users yet",
  EmptyDescription: "Create the first user to give them access.",
  UsersLoadFailed: "The users could not be loaded.",
  PasswordChangePending: "Change pending",
  PasswordActive: "Active",
  CreateUser: "Create user",
  CreateDialogTitle: "Create user",
  CreateDialogDescription:
    "A temporary password is generated for the new user, who must change it at first sign in.",
  NameLabel: "Name",
  EmailLabel: "Email",
  RoleLabel: "Role",
  RolePlaceholder: "Choose a role",
  CreateSubmit: "Create user",
  UserCreated: "The user has been created.",
  DetailTitle: "User details",
  DetailDescription: "Review this account, reset its password and manage its sessions.",
  ProfileTitle: "Profile",
  ProfileDescription: "Account details recorded when the user was created.",
  PasswordStatusLabel: "Password",
  CreatedLabel: "Created",
  ResetPassword: "Reset password",
  ResetTitle: "Reset this password?",
  ResetDescription:
    "A new temporary password replaces the current one. The user is signed out of every device and must change the password at next sign in.",
  ResetOwnDescription:
    "A new temporary password replaces yours. Every one of your sessions ends, including this one, and you must sign in again and change the password.",
  PasswordReset: "The password has been reset.",
  TemporaryPasswordTitle: "Temporary password",
  TemporaryPasswordDescription: "Share this password with the user through a secure channel.",
  TemporaryPasswordWarning:
    "This password will not be shown again. Copy it now before closing this dialog.",
  TemporaryPasswordLabel: "Temporary password",
  Copy: "Copy",
  Copied: "Copied",
  CopyFailed: "The password could not be copied. Select it and copy it manually.",
  SavedPassword: "I have saved the password",
  UserLoadFailed: "This user could not be loaded.",
  UserNotFoundTitle: "User not found",
  UserNotFoundDescription: "This user does not exist.",
  BackToUsers: "Back to users",
} as const;

export const USER_ROLE_OPTIONS: SelectOption<UserRole>[] = ALL_ROLES.map((role) => ({
  value: role,
  label: USER_ROLE_LABELS[role],
}));
