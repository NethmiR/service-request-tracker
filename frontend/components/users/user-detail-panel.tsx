// Content of the user detail page with the profile, the reset password action and the user's sessions.
// When admins reset their own password, the one time dialog is shown first and they are signed out only after closing it.
// A toast confirms the reset of their own password because the route guards then move them away from this page.

"use client";

import Link from "next/link";
import { useState } from "react";
import { SessionsSection } from "@/components/sessions/sessions-section";
import { Alert } from "@/components/shared/alert";
import { Button, getButtonClassNames } from "@/components/shared/button";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { EmptyState } from "@/components/shared/empty-state";
import { Spinner } from "@/components/shared/spinner";
import { TemporaryPasswordDialog } from "@/components/users/temporary-password-dialog";
import { UserProfile } from "@/components/users/user-profile";
import { AppRoute } from "@/constants/routes";
import { USER_SESSIONS_COPY } from "@/constants/session-messages";
import { UiMessage } from "@/constants/ui-messages";
import { UserMessage } from "@/constants/user-messages";
import { ErrorCode } from "@/enums/error-code";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useAuth } from "@/hooks/auth/use-auth";
import { useUserSessions } from "@/hooks/sessions/use-user-sessions";
import { useNotification } from "@/hooks/shared/use-notification";
import { useManagedUser } from "@/hooks/users/use-managed-user";
import { ApiError } from "@/lib/http/api-error";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";
import { messageForError } from "@/lib/http/message-for-error";
import { userService } from "@/services/users/user-service";
import type { IssuedCredentials } from "@/types/users/issued-credentials";

interface UserDetailPanelProps {
  userId: number;
}

export function UserDetailPanel({ userId }: UserDetailPanelProps) {
  const { user: currentUser, refreshUser } = useAuth();
  const { notify } = useNotification();
  const { state, reload } = useManagedUser(userId);
  const userSessions = useUserSessions(userId);
  const [isResetConfirmOpen, setIsResetConfirmOpen] = useState(false);
  const [isResetting, setIsResetting] = useState(false);
  const [credentials, setCredentials] = useState<IssuedCredentials | null>(null);
  const [isRetrying, setIsRetrying] = useState(false);
  const isOwnAccount = currentUser !== null && currentUser.id === userId;

  async function confirmResetPassword() {
    setIsResetting(true);

    try {
      const issuedCredentials = await userService.resetPassword(userId);
      setIsResetConfirmOpen(false);

      if (isOwnAccount && issuedCredentials.temporary_password === null) {
        notify(NotificationType.Success, UserMessage.OwnPasswordReset);
        void refreshUser();
        return;
      }

      setCredentials(issuedCredentials);

      if (isOwnAccount) {
        return;
      }

      notify(NotificationType.Success, UserMessage.PasswordReset);
      void reload();
      void userSessions.reload();
    } catch (error) {
      setIsResetConfirmOpen(false);
      const apiError = error instanceof ApiError ? error : null;

      if (!isHandledByRouteGuards(apiError)) {
        notify(NotificationType.Error, messageForError(apiError));
      }
    } finally {
      setIsResetting(false);
    }
  }

  function handleCredentialsClosed() {
    setCredentials(null);

    if (isOwnAccount) {
      notify(NotificationType.Success, UserMessage.OwnPasswordReset);
      void refreshUser();
    }
  }

  async function handleRetry() {
    setIsRetrying(true);
    await reload();
    setIsRetrying(false);
  }

  function renderContent() {
    const isRedirecting =
      state.status === LoadStatus.Failed && isHandledByRouteGuards(state.error);

    if (state.status === LoadStatus.Loading || isRedirecting) {
      return (
        <div className="flex justify-center py-10 text-primary">
          <Spinner size={ComponentSize.Large} />
        </div>
      );
    }

    if (state.status === LoadStatus.Failed && state.error?.hasCode(ErrorCode.NotFound)) {
      return (
        <EmptyState
          title={UserMessage.UserNotFoundTitle}
          description={UserMessage.UserNotFoundDescription}
          className="rounded-lg border border-border bg-surface"
        />
      );
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <Alert
          tone={AlertTone.Danger}
          title={UserMessage.UserLoadFailed}
          action={
            <Button
              variant={ButtonVariant.Secondary}
              size={ComponentSize.Small}
              isLoading={isRetrying}
              onClick={handleRetry}
            >
              {UiMessage.Retry}
            </Button>
          }
        >
          {messageForError(state.error)}
        </Alert>
      );
    }

    return (
      <>
        <UserProfile
          user={state.user}
          isResetting={isResetting}
          onResetPassword={() => setIsResetConfirmOpen(true)}
        />
        <SessionsSection {...userSessions} copy={USER_SESSIONS_COPY} />
      </>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <Link
        href={AppRoute.Users}
        className={getButtonClassNames(ButtonVariant.Ghost, ComponentSize.Small, "self-start")}
      >
        <span aria-hidden="true">&lsaquo;</span>
        {UserMessage.BackToUsers}
      </Link>
      {renderContent()}
      <ConfirmDialog
        open={isResetConfirmOpen}
        title={UserMessage.ResetTitle}
        description={isOwnAccount ? UserMessage.ResetOwnDescription : UserMessage.ResetDescription}
        confirmLabel={UserMessage.ResetPassword}
        isConfirming={isResetting}
        onConfirm={() => void confirmResetPassword()}
        onCancel={() => setIsResetConfirmOpen(false)}
      />
      <TemporaryPasswordDialog credentials={credentials} onClose={handleCredentialsClosed} />
    </div>
  );
}
