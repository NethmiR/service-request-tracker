// Section that lists active sessions and ends one session or every session that can be ended.
// Used by the account and user detail pages, which pass their own data source and text through props.

"use client";

import { useState } from "react";
import { SessionsTable } from "@/components/sessions/sessions-table";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { ContentSection } from "@/components/shared/content-section";
import { Spinner } from "@/components/shared/spinner";
import { SessionMessage } from "@/constants/session-messages";
import { UiMessage } from "@/constants/ui-messages";
import { ErrorCode } from "@/enums/error-code";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import type { SessionList } from "@/hooks/sessions/use-session-list";
import { ApiError } from "@/lib/http/api-error";
import { messageForError } from "@/lib/http/message-for-error";
import type { SessionSectionCopy } from "@/types/sessions/session-section-copy";
import type { UserSession } from "@/types/sessions/user-session";

const SILENTLY_HANDLED_ERROR_CODES: ReadonlySet<ErrorCode> = new Set([
  ErrorCode.Unauthenticated,
  ErrorCode.PasswordChangeRequired,
]);

interface SessionsSectionProps extends SessionList {
  copy: SessionSectionCopy;
}

export function SessionsSection({
  state,
  reload,
  clearSession,
  clearSessions,
  copy,
}: SessionsSectionProps) {
  const { notify } = useNotification();
  const [sessionToEnd, setSessionToEnd] = useState<UserSession | null>(null);
  const [isEndAllOpen, setIsEndAllOpen] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);
  const [isRetrying, setIsRetrying] = useState(false);

  const hasClearableSessions =
    state.status === LoadStatus.Loaded && state.sessions.some((session) => !session.is_current);

  function handleFailure(error: ApiError | null) {
    if (error !== null && SILENTLY_HANDLED_ERROR_CODES.has(error.code)) {
      return;
    }

    if (error !== null && error.hasCode(ErrorCode.NotFound)) {
      notify(NotificationType.Info, SessionMessage.SessionAlreadyEnded);
      void reload();
      return;
    }

    notify(NotificationType.Error, messageForError(error));
  }

  async function runConfirmedAction(action: () => Promise<void>, successMessage: string) {
    setIsConfirming(true);

    try {
      await action();
      notify(NotificationType.Success, successMessage);
    } catch (error) {
      handleFailure(error instanceof ApiError ? error : null);
    } finally {
      setIsConfirming(false);
      setSessionToEnd(null);
      setIsEndAllOpen(false);
    }
  }

  function confirmEndSession() {
    if (sessionToEnd !== null) {
      void runConfirmedAction(() => clearSession(sessionToEnd), SessionMessage.SessionEnded);
    }
  }

  function confirmEndAllSessions() {
    void runConfirmedAction(clearSessions, copy.endAllSucceeded);
  }

  async function handleRetry() {
    setIsRetrying(true);
    await reload();
    setIsRetrying(false);
  }

  function renderActions() {
    if (state.status !== LoadStatus.Loaded) {
      return undefined;
    }

    if (!hasClearableSessions) {
      return <p className="text-sm text-muted">{copy.nothingToEnd}</p>;
    }

    return (
      <Button
        variant={ButtonVariant.Danger}
        size={ComponentSize.Small}
        onClick={() => setIsEndAllOpen(true)}
        disabled={isConfirming}
      >
        {copy.endAllLabel}
      </Button>
    );
  }

  function renderContent() {
    if (state.status === LoadStatus.Loading) {
      return (
        <div className="flex justify-center py-10 text-primary">
          <Spinner size={ComponentSize.Large} />
        </div>
      );
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <Alert
          tone={AlertTone.Danger}
          title={copy.loadFailed}
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
      <SessionsTable
        sessions={state.sessions}
        onClearSession={setSessionToEnd}
        clearingSessionId={isConfirming && sessionToEnd !== null ? sessionToEnd.id : null}
      />
    );
  }

  return (
    <ContentSection
      title={copy.title}
      description={copy.description}
      actions={renderActions()}
    >
      {renderContent()}
      <ConfirmDialog
        open={sessionToEnd !== null}
        title={SessionMessage.EndSessionTitle}
        description={SessionMessage.EndSessionDescription}
        confirmLabel={SessionMessage.EndSession}
        isConfirming={isConfirming}
        onConfirm={confirmEndSession}
        onCancel={() => setSessionToEnd(null)}
      />
      <ConfirmDialog
        open={isEndAllOpen}
        title={copy.endAllTitle}
        description={copy.endAllDescription}
        confirmLabel={copy.endAllLabel}
        isConfirming={isConfirming}
        onConfirm={confirmEndAllSessions}
        onCancel={() => setIsEndAllOpen(false)}
      />
    </ContentSection>
  );
}
