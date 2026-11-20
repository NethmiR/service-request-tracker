// Route level error boundary shown when a page fails to render.
// Shows a generic message and lets the user retry without revealing error details.

"use client";

import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import type { ErrorBoundaryProps } from "@/types/shared/error-boundary-props";

export default function RouteError({ retry }: ErrorBoundaryProps) {
  return (
    <main className="flex flex-1 items-center justify-center p-4 sm:p-8">
      <Alert
        tone={AlertTone.Danger}
        title={UiMessage.GenericErrorTitle}
        action={<Button onClick={retry}>{UiMessage.Retry}</Button>}
        className="w-full max-w-xl bg-surface"
      >
        {UiMessage.GenericErrorDescription}
      </Alert>
    </main>
  );
}
