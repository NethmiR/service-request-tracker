// Text for the sign in, change password and sign out features.
// Shared by the public sign in page, the change password page and the sidebar sign out action.

export const AuthMessage = {
  SignInTitle: "Sign in",
  SignInDescription: "Use your work email and password to continue.",
  EmailLabel: "Email",
  PasswordLabel: "Password",
  SignInButton: "Sign in",
  ChangePasswordTitle: "Change password",
  ChangePasswordDescription: "Choose a new password for your account.",
  PasswordChangeRequiredTitle: "Password change required",
  PasswordChangeRequiredDescription:
    "Your password was set by an administrator. Choose a new password before you continue.",
  CurrentPasswordLabel: "Current password",
  NewPasswordLabel: "New password",
  ConfirmPasswordLabel: "Confirm new password",
  ChangePasswordButton: "Change password",
  PasswordChanged: "Your password has been changed.",
  SignOutButton: "Sign out",
  SignOutFailed: "You could not be signed out. Please try again.",
} as const;
