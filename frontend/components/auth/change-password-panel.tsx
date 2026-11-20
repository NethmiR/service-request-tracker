// Content of the change password page, explaining a pending change when one is required.
// Sends the user to their home page after a successful change and always offers sign out.

"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { ChangePasswordForm } from "@/components/auth/change-password-form";
import { SignOutButton } from "@/components/auth/sign-out-button";
import { Alert } from "@/components/shared/alert";
import { getButtonClassNames } from "@/components/shared/button";
import { PageHeader } from "@/components/shared/page-header";
import { AuthMessage } from "@/constants/auth-messages";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { useAuth } from "@/hooks/auth/use-auth";
import { resolveSignedInRoute } from "@/lib/auth/resolve-signed-in-route";
import type { CurrentUser } from "@/types/auth/current-user";

export function ChangePasswordPanel() {
  const { user } = useAuth();
  const router = useRouter();
  const isChangeRequired = user?.password_change_required ?? false;

  function handleSuccess(updatedUser: CurrentUser) {
    router.replace(resolveSignedInRoute(updatedUser));
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={AuthMessage.ChangePasswordTitle}
        description={AuthMessage.ChangePasswordDescription}
      />
      {isChangeRequired && (
        <Alert tone={AlertTone.Warning} title={AuthMessage.PasswordChangeRequiredTitle}>
          {AuthMessage.PasswordChangeRequiredDescription}
        </Alert>
      )}
      <ChangePasswordForm onSuccess={handleSuccess} />
      <div className="flex items-center justify-between gap-3 border-t border-border-subtle pt-5">
        {user !== null && !isChangeRequired ? (
          <Link
            href={resolveSignedInRoute(user)}
            className={getButtonClassNames(ButtonVariant.Ghost)}
          >
            {UiMessage.BackToHome}
          </Link>
        ) : (
          <span />
        )}
        <SignOutButton variant={ButtonVariant.Ghost} />
      </div>
    </div>
  );
}
