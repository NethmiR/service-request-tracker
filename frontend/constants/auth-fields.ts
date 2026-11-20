// Field names of the sign in and change password forms.
// Typed against the request types, so a renamed field fails the type check.

import type { ChangePasswordRequest } from "@/types/auth/change-password-request";
import type { SignInRequest } from "@/types/auth/sign-in-request";

export const SignInField = {
  Email: "email",
  Password: "password",
} as const satisfies Record<string, keyof SignInRequest>;

export const ChangePasswordField = {
  CurrentPassword: "current_password",
  Password: "password",
  PasswordConfirmation: "password_confirmation",
} as const satisfies Record<string, keyof ChangePasswordRequest>;
