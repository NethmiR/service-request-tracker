// Guard for signed in pages that sends guests to sign in and pending users to change password.
// Shows the page loader until the auth check settles so protected content never flashes.

"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import type { ReactNode } from "react";
import { PageLoader } from "@/components/shared/page-loader";
import { AppRoute } from "@/constants/routes";
import { AuthStatus } from "@/enums/ui/auth-status";
import { useAuth } from "@/hooks/auth/use-auth";
import type { CurrentUser } from "@/types/auth/current-user";

function resolveRedirect(
  status: AuthStatus,
  user: CurrentUser | null,
  pathname: string,
): AppRoute | null {
  if (status === AuthStatus.Unauthenticated) {
    return AppRoute.SignIn;
  }

  if (user?.password_change_required && pathname !== AppRoute.ChangePassword) {
    return AppRoute.ChangePassword;
  }

  return null;
}

interface RequireAuthenticationProps {
  children: ReactNode;
}

export function RequireAuthentication({ children }: RequireAuthenticationProps) {
  const { status, user } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const redirectTarget = resolveRedirect(status, user, pathname);

  useEffect(() => {
    if (redirectTarget !== null) {
      router.replace(redirectTarget);
    }
  }, [redirectTarget, router]);

  if (status === AuthStatus.Loading || redirectTarget !== null) {
    return <PageLoader />;
  }

  return children;
}
