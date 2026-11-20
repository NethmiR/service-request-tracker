// Workspace layout of every signed in page except change password, rendered inside the sidebar shell.
// The shell shows the role based navigation and the account summary next to the content panel.

import type { ReactNode } from "react";
import { WorkspaceShell } from "@/components/shared/workspace-shell";

interface WorkspaceLayoutProps {
  children: ReactNode;
}

export default function WorkspaceLayout({ children }: WorkspaceLayoutProps) {
  return <WorkspaceShell>{children}</WorkspaceShell>;
}
