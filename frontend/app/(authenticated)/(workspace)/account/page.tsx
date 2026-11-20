// Account page where every role reviews their profile, changes their password and manages sessions.
// The mandatory password change stays on its own page outside the workspace layout.

import type { Metadata } from "next";
import { AccountPanel } from "@/components/auth/account-panel";
import { PageHeader } from "@/components/shared/page-header";
import { AccountMessage } from "@/constants/account-messages";

export const metadata: Metadata = {
  title: AccountMessage.PageTitle,
};

export default function AccountPage() {
  return (
    <>
      <PageHeader title={AccountMessage.PageTitle} description={AccountMessage.PageDescription} />
      <AccountPanel />
    </>
  );
}
