// One time dialog that shows a newly issued temporary password with a copy button.
// It closes only through its confirmation button, and the password lives only in the parent's state.

"use client";

import { useState } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { Dialog } from "@/components/shared/dialog";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { UserMessage } from "@/constants/user-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { AutocompleteToken } from "@/enums/ui/autocomplete-token";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import type { IssuedCredentials } from "@/types/users/issued-credentials";

interface TemporaryPasswordDialogProps {
  credentials: IssuedCredentials | null;
  onClose: () => void;
}

export function TemporaryPasswordDialog({ credentials, onClose }: TemporaryPasswordDialogProps) {
  const { notify } = useNotification();
  const [copiedPassword, setCopiedPassword] = useState<string | null>(null);
  const temporaryPassword = credentials?.temporary_password ?? null;
  const isOpen = credentials !== null && temporaryPassword !== null;
  const isCopied = temporaryPassword !== null && copiedPassword === temporaryPassword;

  async function handleCopy() {
    if (temporaryPassword === null) {
      return;
    }

    try {
      await navigator.clipboard.writeText(temporaryPassword);
      setCopiedPassword(temporaryPassword);
    } catch {
      notify(NotificationType.Error, UserMessage.CopyFailed);
    }
  }

  function handleClose() {
    setCopiedPassword(null);
    onClose();
  }

  return (
    <Dialog
      open={isOpen}
      onClose={handleClose}
      title={UserMessage.TemporaryPasswordTitle}
      description={UserMessage.TemporaryPasswordDescription}
      dismissible={false}
      footer={<Button onClick={handleClose}>{UserMessage.SavedPassword}</Button>}
    >
      {credentials !== null && temporaryPassword !== null && (
        <div className="flex flex-col gap-5">
          <div className="flex min-w-0 flex-col gap-0.5">
            <p className="truncate text-base font-semibold text-foreground">
              {credentials.user.name}
            </p>
            <p className="truncate text-sm text-muted" title={credentials.user.email}>
              {credentials.user.email}
            </p>
          </div>
          <FormField label={UserMessage.TemporaryPasswordLabel}>
            {(controlProps) => (
              <div className="flex gap-2">
                <Input
                  {...controlProps}
                  readOnly
                  value={temporaryPassword}
                  spellCheck={false}
                  autoComplete={AutocompleteToken.Off}
                  onFocus={(event) => event.currentTarget.select()}
                  className="font-mono tracking-wide"
                />
                <Button variant={ButtonVariant.Secondary} onClick={handleCopy}>
                  {isCopied ? UserMessage.Copied : UserMessage.Copy}
                </Button>
              </div>
            )}
          </FormField>
          <Alert tone={AlertTone.Warning}>{UserMessage.TemporaryPasswordWarning}</Alert>
        </div>
      )}
    </Dialog>
  );
}
