// Guard for the signed out pages that sends signed in users to where they belong.
// Also performs the navigation after a successful sign in, so the form never navigates itself.

"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import type { ReactNode } from "react";
import { PageLoader } from "@/components/shared/page-loader";
import { AuthStatus } from "@/enums/ui/auth-status";
import { useAuth } from "@/hooks/auth/use-auth";
import { resolveSignedInRoute } from "@/lib/auth/resolve-signed-in-route";

interface RequireGuestProps {
  children: ReactNode;
}

export function RequireGuest({ children }: RequireGuestProps) {
  const { status, user } = useAuth();
  const router = useRouter();
  const redirectTarget = user !== null ? resolveSignedInRoute(user) : null;

  useEffect(() => {
    if (redirectTarget !== null) {
      router.replace(redirectTarget);
    }
  }, [redirectTarget, router]);

  if (status !== AuthStatus.Unauthenticated) {
    return <PageLoader />;
  }

  return children;
}
