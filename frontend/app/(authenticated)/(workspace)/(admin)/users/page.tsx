// Users page where admins see every account, create users and open a user's details.
// The list and the dialogs live in a client panel because they hold interactive state.

import type { Metadata } from "next";
import { PageHeader } from "@/components/shared/page-header";
import { UsersPanel } from "@/components/users/users-panel";
import { UserMessage } from "@/constants/user-messages";

export const metadata: Metadata = {
  title: UserMessage.PageTitle,
};

export default function UsersPage() {
  return (
    <>
      <PageHeader title={UserMessage.PageTitle} description={UserMessage.PageDescription} />
      <UsersPanel />
    </>
  );
}
