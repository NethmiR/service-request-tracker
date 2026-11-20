// Guard for pages limited to some roles that sends any other role to its own home page.
// Always rendered inside RequireAuthentication, and the backend still rejects every disallowed data call.

"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import type { ReactNode } from "react";
import { PageLoader } from "@/components/shared/page-loader";
import { ROLE_HOME_ROUTES, type AppRoute } from "@/constants/routes";
import type { UserRole } from "@/enums/user-role";
import { useAuth } from "@/hooks/auth/use-auth";

interface RequireRoleProps {
  allowedRoles: readonly UserRole[];
  children: ReactNode;
}

export function RequireRole({ allowedRoles, children }: RequireRoleProps) {
  const { user } = useAuth();
  const router = useRouter();
  const redirectTarget: AppRoute | null =
    user !== null && !allowedRoles.includes(user.role) ? ROLE_HOME_ROUTES[user.role] : null;

  useEffect(() => {
    if (redirectTarget !== null) {
      router.replace(redirectTarget);
    }
  }, [redirectTarget, router]);

  if (user === null || redirectTarget !== null) {
    return <PageLoader />;
  }

  return children;
}
