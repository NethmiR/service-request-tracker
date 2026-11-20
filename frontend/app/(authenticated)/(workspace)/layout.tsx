// Temporary workspace layout with a top bar above a white content panel.
// Phase 3 replaces this file with the sidebar layout without affecting the change password page.

import type { ReactNode } from "react";
import { WorkspaceTopBar } from "@/components/shared/workspace-top-bar";

interface WorkspaceLayoutProps {
  children: ReactNode;
}

export default function WorkspaceLayout({ children }: WorkspaceLayoutProps) {
  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <WorkspaceTopBar />
      <main className="flex flex-1 flex-col gap-6 rounded-lg bg-surface p-6 sm:p-8">
        {children}
      </main>
    </div>
  );
}
