// Last resort error boundary that replaces the root layout when it fails.
// Renders its own html and body and loads the global styles itself, as Next.js requires.

"use client";

import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { APPLICATION_NAME } from "@/constants/application";
import { UiMessage } from "@/constants/ui-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import type { ErrorBoundaryProps } from "@/types/shared/error-boundary-props";
import "./globals.css";

export default function GlobalError({ retry }: ErrorBoundaryProps) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="flex min-h-full flex-col">
        <title>{APPLICATION_NAME}</title>
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
      </body>
    </html>
  );
}
