// Layout of the signed out pages, showing the brand above a centred card.
// RequireGuest sends signed in users to their home page instead of showing the content.

import type { ReactNode } from "react";
import { RequireGuest } from "@/components/auth/require-guest";
import { CenteredPanel } from "@/components/shared/centered-panel";

interface PublicLayoutProps {
  children: ReactNode;
}

export default function PublicLayout({ children }: PublicLayoutProps) {
  return (
    <CenteredPanel>
      <RequireGuest>{children}</RequireGuest>
    </CenteredPanel>
  );
}
