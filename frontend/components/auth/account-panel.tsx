// Content of the account page with the profile, the password change form and the active sessions.
// Reloads the sessions after a password change because it ends every other session.

"use client";

import { AccountProfile } from "@/components/auth/account-profile";
import { ChangePasswordForm } from "@/components/auth/change-password-form";
import { ActiveSessionsSection } from "@/components/sessions/active-sessions-section";
import { ContentSection } from "@/components/shared/content-section";
import { AccountMessage } from "@/constants/account-messages";
import { useOwnSessions } from "@/hooks/sessions/use-own-sessions";

export function AccountPanel() {
  const ownSessions = useOwnSessions();
  const { reload } = ownSessions;

  function handlePasswordChanged() {
    void reload();
  }

  return (
    <div className="flex flex-col gap-6">
      <AccountProfile />
      <ContentSection
        title={AccountMessage.PasswordTitle}
        description={AccountMessage.PasswordDescription}
      >
        <div className="max-w-xl">
          <ChangePasswordForm onSuccess={handlePasswordChanged} />
        </div>
      </ContentSection>
      <ActiveSessionsSection {...ownSessions} />
    </div>
  );
}
