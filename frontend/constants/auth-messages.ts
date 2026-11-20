// Text for the sign in, change password and sign out features.
// Also holds the placeholder text of the overview page until Phase 7 replaces it.

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
  OverviewTitle: "Overview",
  OverviewPlaceholder: "The overview of service requests will appear here.",
} as const;
