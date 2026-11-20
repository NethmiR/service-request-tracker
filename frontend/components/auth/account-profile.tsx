// Account page section that shows the signed in user's name, email and role.
// The details are read only because only admins manage user accounts.

"use client";

import { Badge } from "@/components/shared/badge";
import { ContentSection } from "@/components/shared/content-section";
import { AccountMessage } from "@/constants/account-messages";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { useAuth } from "@/hooks/auth/use-auth";

export function AccountProfile() {
  const { user } = useAuth();

  if (user === null) {
    return null;
  }

  return (
    <ContentSection title={AccountMessage.ProfileTitle} description={AccountMessage.ProfileDescription}>
      <dl className="grid gap-4 sm:grid-cols-3">
        <div className="flex min-w-0 flex-col gap-1">
          <dt className="text-sm font-medium text-muted">{AccountMessage.NameLabel}</dt>
          <dd className="truncate text-base text-foreground">{user.name}</dd>
        </div>
        <div className="flex min-w-0 flex-col gap-1">
          <dt className="text-sm font-medium text-muted">{AccountMessage.EmailLabel}</dt>
          <dd className="truncate text-base text-foreground" title={user.email}>
            {user.email}
          </dd>
        </div>
        <div className="flex flex-col items-start gap-1">
          <dt className="text-sm font-medium text-muted">{AccountMessage.RoleLabel}</dt>
          <dd>
            <Badge tone={BadgeTone.Info}>{USER_ROLE_LABELS[user.role]}</Badge>
          </dd>
        </div>
      </dl>
    </ContentSection>
  );
}
