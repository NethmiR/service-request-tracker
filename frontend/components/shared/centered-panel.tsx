// Full page layout with the brand above a centred white card.
// Used by the sign in page and the change password page, which sit outside the workspace layout.

import type { ReactNode } from "react";
import { Brand } from "@/components/shared/brand";

interface CenteredPanelProps {
  children: ReactNode;
}

export function CenteredPanel({ children }: CenteredPanelProps) {
  return (
    <main className="flex flex-1 flex-col items-center justify-center gap-6 p-4 sm:p-8">
      <Brand />
      <div className="flex w-full max-w-md flex-col rounded-lg bg-surface p-6 shadow-sm sm:p-8">
        {children}
      </div>
    </main>
  );
}
