// Returns the auth context with the signed in user and the authentication actions.
// Throws a clear error when used outside AuthProvider.

import { use } from "react";
import { DeveloperMessage } from "@/constants/developer-messages";
import { AuthContext, type AuthContextValue } from "@/contexts/auth-context";

export function useAuth(): AuthContextValue {
  const context = use(AuthContext);

  if (context === null) {
    throw new Error(DeveloperMessage.AuthProviderMissing);
  }

  return context;
}
