// Change password page, used for the mandatory change and for voluntary changes.
// Sits outside the workspace layout so a pending user sees only this page and sign out.

import type { Metadata } from "next";
import { ChangePasswordPanel } from "@/components/auth/change-password-panel";
import { CenteredPanel } from "@/components/shared/centered-panel";
import { AuthMessage } from "@/constants/auth-messages";

export const metadata: Metadata = {
  title: AuthMessage.ChangePasswordTitle,
};

export default function ChangePasswordPage() {
  return (
    <CenteredPanel>
      <ChangePasswordPanel />
    </CenteredPanel>
  );
}
