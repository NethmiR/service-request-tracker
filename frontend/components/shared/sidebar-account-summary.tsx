// Account summary at the bottom of the sidebar with the user's name, email and role.
// Holds the sign out button, which is the only way to end the current session.

"use client";

import { SignOutButton } from "@/components/auth/sign-out-button";
import { Badge } from "@/components/shared/badge";
import { NavigationMessage } from "@/constants/navigation";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { useAuth } from "@/hooks/auth/use-auth";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface SidebarAccountSummaryProps {
  className?: string;
}

export function SidebarAccountSummary({ className }: SidebarAccountSummaryProps) {
  const { user } = useAuth();

  if (user === null) {
    return null;
  }

  return (
    <section
      aria-label={NavigationMessage.AccountSummaryLabel}
      className={mergeClassNames("flex flex-col gap-4 border-t border-border-subtle pt-4", className)}
    >
      <div className="flex min-w-0 flex-col gap-1 px-3">
        <span className="truncate text-sm font-semibold text-foreground">{user.name}</span>
        <span className="truncate text-sm text-muted" title={user.email}>
          {user.email}
        </span>
        <Badge tone={BadgeTone.Info} className="mt-1 self-start">
          {USER_ROLE_LABELS[user.role]}
        </Badge>
      </div>
      <SignOutButton variant={ButtonVariant.Secondary} className="w-full" />
    </section>
  );
}
