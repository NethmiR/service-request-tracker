// Sends visitors of the root route to their signed in home page or to sign in.
// Always renders the page loader, because the root route has no content of its own.

"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { PageLoader } from "@/components/shared/page-loader";
import { AppRoute } from "@/constants/routes";
import { AuthStatus } from "@/enums/ui/auth-status";
import { useAuth } from "@/hooks/auth/use-auth";
import { resolveSignedInRoute } from "@/lib/auth/resolve-signed-in-route";

export function RootRedirect() {
  const { status, user } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (user !== null) {
      router.replace(resolveSignedInRoute(user));
      return;
    }

    if (status === AuthStatus.Unauthenticated) {
      router.replace(AppRoute.SignIn);
    }
  }, [status, user, router]);

  return <PageLoader />;
}
