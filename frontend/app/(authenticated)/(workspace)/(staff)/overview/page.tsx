// Overview page for technicians and admins with the request counts, the highlights to review and the long term trends.
// The page itself is a server component, and the panel loads the figures in the browser.

import type { Metadata } from "next";
import { OverviewPanel } from "@/components/overview/overview-panel";
import { PageHeader } from "@/components/shared/page-header";
import { OverviewMessage } from "@/constants/overview-messages";

export const metadata: Metadata = {
  title: OverviewMessage.PageTitle,
};

export default function OverviewPage() {
  return (
    <>
      <PageHeader title={OverviewMessage.PageTitle} description={OverviewMessage.PageDescription} />
      <OverviewPanel />
    </>
  );
}
