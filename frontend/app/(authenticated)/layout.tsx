// Layout of every signed in page, guarded by RequireAuthentication.
// Guests go to sign in and users with a pending password change go to change password.

import type { ReactNode } from "react";
import { RequireAuthentication } from "@/components/auth/require-authentication";

interface AuthenticatedLayoutProps {
  children: ReactNode;
}

export default function AuthenticatedLayout({ children }: AuthenticatedLayoutProps) {
  return <RequireAuthentication>{children}</RequireAuthentication>;
}
