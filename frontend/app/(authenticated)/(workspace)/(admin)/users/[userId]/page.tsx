// User detail page where admins review an account, reset its password and manage its sessions.
// An id that is not a positive whole number shows the not found page before any request is sent.

import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PageHeader } from "@/components/shared/page-header";
import { UserDetailPanel } from "@/components/users/user-detail-panel";
import { POSITIVE_WHOLE_NUMBER_PATTERN } from "@/constants/route-parameters";
import { UserMessage } from "@/constants/user-messages";

export const metadata: Metadata = {
  title: UserMessage.DetailTitle,
};

export default async function UserDetailPage({ params }: PageProps<"/users/[userId]">) {
  const { userId } = await params;

  if (!POSITIVE_WHOLE_NUMBER_PATTERN.test(userId)) {
    notFound();
  }

  return (
    <>
      <PageHeader title={UserMessage.DetailTitle} description={UserMessage.DetailDescription} />
      <UserDetailPanel userId={Number(userId)} />
    </>
  );
}
