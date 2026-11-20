// Content of the users page with the paginated users list, the create user dialog and the temporary password dialog.
// After a user is created it returns to the first page, where the newest user appears at the top.

"use client";

import { useState } from "react";
import { Button } from "@/components/shared/button";
import { ContentSection } from "@/components/shared/content-section";
import { LoadFailureAlert } from "@/components/shared/load-failure-alert";
import { Pagination } from "@/components/shared/pagination";
import { SectionLoader } from "@/components/shared/section-loader";
import { CreateUserDialog } from "@/components/users/create-user-dialog";
import { TemporaryPasswordDialog } from "@/components/users/temporary-password-dialog";
import { UsersTable } from "@/components/users/users-table";
import { FIRST_PAGE } from "@/constants/http";
import { UserMessage } from "@/constants/user-messages";
import { LoadStatus } from "@/enums/ui/load-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import { useUsers } from "@/hooks/users/use-users";
import type { IssuedCredentials } from "@/types/users/issued-credentials";

export function UsersPanel() {
  const { notify } = useNotification();
  const { state, page, goToPage, isChangingPage, reload } = useUsers();
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [credentials, setCredentials] = useState<IssuedCredentials | null>(null);

  function handleCreated(issuedCredentials: IssuedCredentials) {
    setIsCreateOpen(false);
    setCredentials(issuedCredentials);
    notify(NotificationType.Success, UserMessage.UserCreated);

    if (page === FIRST_PAGE) {
      void reload();
      return;
    }

    goToPage(FIRST_PAGE);
  }

  function renderContent() {
    if (state.status === LoadStatus.Loading) {
      return <SectionLoader />;
    }

    if (state.status === LoadStatus.Failed) {
      return (
        <LoadFailureAlert title={UserMessage.UsersLoadFailed} error={state.error} onRetry={reload} />
      );
    }

    return (
      <div className="flex flex-col gap-4" aria-busy={isChangingPage || undefined}>
        <UsersTable users={state.page.data} />
        <Pagination meta={state.page.meta} onPageChange={goToPage} disabled={isChangingPage} />
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
