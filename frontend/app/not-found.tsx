// Page shown for unknown routes and whenever notFound is called.
// Offers a single link back to the root route.

import Link from "next/link";
import { getButtonClassNames } from "@/components/shared/button";
import { EmptyState } from "@/components/shared/empty-state";
import { AppRoute } from "@/constants/routes";
import { UiMessage } from "@/constants/ui-messages";

export default function NotFound() {
  return (
    <main className="flex flex-1 items-center justify-center p-4 sm:p-8">
      <EmptyState
        title={UiMessage.NotFoundTitle}
        description={UiMessage.NotFoundDescription}
        action={
          <Link href={AppRoute.Root} className={getButtonClassNames()}>
            {UiMessage.BackToHome}
          </Link>
        }
        className="w-full max-w-xl rounded-lg border border-border bg-surface"
      />
    </main>
  );
}
