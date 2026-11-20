// Temporary top bar of the signed in workspace with the brand, the signed in user and sign out.
// Phase 3 replaces it with the sidebar layout and role based navigation.

"use client";

import { SignOutButton } from "@/components/auth/sign-out-button";
import { Badge } from "@/components/shared/badge";
import { Brand } from "@/components/shared/brand";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { useAuth } from "@/hooks/auth/use-auth";

export function WorkspaceTopBar() {
  const { user } = useAuth();

  return (
    <header className="flex flex-col gap-4 rounded-lg bg-surface px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <Brand />
      {user !== null && (
        <div className="flex items-center justify-between gap-4 sm:justify-end">
          <div className="flex flex-col items-start sm:items-end">
            <span className="text-sm font-semibold text-foreground">{user.name}</span>
            <Badge tone={BadgeTone.Info} className="mt-1">
              {USER_ROLE_LABELS[user.role]}
            </Badge>
          </div>
          <SignOutButton variant={ButtonVariant.Secondary} />
        </div>
      )}
    </header>
  );
}
