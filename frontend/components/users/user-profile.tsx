// Profile section of the user detail page with the name, email, role, password status and creation date.
// Holds the reset password button, while the parent owns the confirmation and the request.

import { Badge } from "@/components/shared/badge";
import { Button } from "@/components/shared/button";
import { ContentSection } from "@/components/shared/content-section";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { UserMessage } from "@/constants/user-messages";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { passwordStatusLabel, passwordStatusTone } from "@/lib/users/password-status";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { ManagedUser } from "@/types/users/managed-user";

interface UserProfileProps {
  user: ManagedUser;
  isResetting: boolean;
  onResetPassword: () => void;
}

export function UserProfile({ user, isResetting, onResetPassword }: UserProfileProps) {
  return (
    <ContentSection
      title={UserMessage.ProfileTitle}
      description={UserMessage.ProfileDescription}
      actions={
        <Button
          variant={ButtonVariant.Danger}
          size={ComponentSize.Small}
          isLoading={isResetting}
          onClick={onResetPassword}
        >
          {UserMessage.ResetPassword}
        </Button>
      }
    >
      <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="flex min-w-0 flex-col gap-1">
          <dt className="text-sm font-medium text-muted">{UserMessage.NameLabel}</dt>
          <dd className="truncate text-base text-foreground">{user.name}</dd>
        </div>
        <div className="flex min-w-0 flex-col gap-1">
          <dt className="text-sm font-medium text-muted">{UserMessage.EmailLabel}</dt>
          <dd className="truncate text-base text-foreground" title={user.email}>
            {user.email}
          </dd>
        </div>
        <div className="flex flex-col items-start gap-1">
          <dt className="text-sm font-medium text-muted">{UserMessage.RoleLabel}</dt>
          <dd>
            <Badge tone={BadgeTone.Info}>{USER_ROLE_LABELS[user.role]}</Badge>
          </dd>
        </div>
        <div className="flex flex-col items-start gap-1">
          <dt className="text-sm font-medium text-muted">{UserMessage.PasswordStatusLabel}</dt>
          <dd>
            <Badge tone={passwordStatusTone(user)}>{passwordStatusLabel(user)}</Badge>
          </dd>
        </div>
        <div className="flex flex-col gap-1">
          <dt className="text-sm font-medium text-muted">{UserMessage.CreatedLabel}</dt>
          <dd className="text-base text-foreground">
            <time dateTime={user.created_at}>{formatDateTime(user.created_at)}</time>
          </dd>
        </div>
      </dl>
    </ContentSection>
  );
}
