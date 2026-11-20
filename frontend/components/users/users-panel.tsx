// Content of the users page with the paginated users list, the create user dialog and the temporary password dialog.
// After a user is created it returns to the first page, where the newest user appears at the top.

"use client";

import { useState } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { ContentSection } from "@/components/shared/content-section";
import { Pagination } from "@/components/shared/pagination";
import { Spinner } from "@/components/shared/spinner";
import { CreateUserDialog } from "@/components/users/create-user-dialog";
import { TemporaryPasswordDialog } from "@/components/users/temporary-password-dialog";
import { UsersTable } from "@/components/users/users-table";
import { UiMessage } from "@/constants/ui-messages";
import { UserMessage } from "@/constants/user-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import { FIRST_USERS_PAGE, useUsers } from "@/hooks/users/use-users";
import { messageForError } from "@/lib/http/message-for-error";
import type { IssuedCredentials } from "@/types/users/issued-credentials";

export function UsersPanel() {
  const { notify } = useNotification();
  const { state, page, goToPage, isChangingPage, reload } = useUsers();
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [credentials, setCredentials] = useState<IssuedCredentials | null>(null);
  const [isRetrying, setIsRetrying] = useState(false);

  function handleCreated(issuedCredentials: IssuedCredentials) {
    setIsCreateOpen(false);
    setCredentials(issuedCredentials);
    notify(NotificationType.Success, UserMessage.UserCreated);

    if (page === FIRST_USERS_PAGE) {
      void reload();
      return;
    }

    goToPage(FIRST_USERS_PAGE);
  }

  async function handleRetry() {
    setIsRetrying(true);
    await reload();
    setIsRetrying(false);
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
          title={UserMessage.UsersLoadFailed}
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
      <div className="flex flex-col gap-4" aria-busy={isChangingPage || undefined}>
        <UsersTable users={state.users.data} />
        <Pagination meta={state.users.meta} onPageChange={goToPage} disabled={isChangingPage} />
      </div>
    );
  }

  return (
    <ContentSection
      title={UserMessage.ListTitle}
      description={UserMessage.ListDescription}
      actions={<Button onClick={() => setIsCreateOpen(true)}>{UserMessage.CreateUser}</Button>}
    >
      {renderContent()}
      <CreateUserDialog
        open={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        onCreated={handleCreated}
      />
      <TemporaryPasswordDialog credentials={credentials} onClose={() => setCredentials(null)} />
    </ContentSection>
  );
}
