// Danger alert shown when a section fails to load, with the error message and a retry button.
// Owns the loading state of the retry button so callers only pass the retry action.

"use client";

import { useState } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import type { ApiError } from "@/lib/http/api-error";
import { messageForError } from "@/lib/http/message-for-error";

interface LoadFailureAlertProps {
  title: string;
  error: ApiError | null;
  onRetry: () => Promise<void>;
}

export function LoadFailureAlert({ title, error, onRetry }: LoadFailureAlertProps) {
  const [isRetrying, setIsRetrying] = useState(false);

  async function handleRetry() {
    setIsRetrying(true);

    try {
      await onRetry();
    } finally {
      setIsRetrying(false);
    }
  }

  return (
    <Alert
      tone={AlertTone.Danger}
      title={title}
      action={
        <Button
          variant={ButtonVariant.Secondary}
          size={ComponentSize.Small}
          isLoading={isRetrying}
          onClick={() => void handleRetry()}
        >
          {UiMessage.Retry}
        </Button>
      }
    >
      {messageForError(error)}
    </Alert>
  );
}
