// Presentational table of the signed in user's service requests with title, tags, status, assignee and creation time.
// The empty state links to the new request page, and the table holds no data loading.

import Link from "next/link";
import {
  SERVICE_REQUEST_ASSIGNEE_COLUMN,
  SERVICE_REQUEST_CREATED_COLUMN,
  SERVICE_REQUEST_STATUS_COLUMN,
  SERVICE_REQUEST_TAGS_COLUMN,
  SERVICE_REQUEST_TITLE_COLUMN,
} from "@/components/service-requests/service-request-columns";
import { getButtonClassNames } from "@/components/shared/button";
import { Table, type TableColumn } from "@/components/shared/table";
import { AppRoute } from "@/constants/routes";
import { ServiceRequestMessage } from "@/constants/service-request-messages";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";

const MY_SERVICE_REQUEST_COLUMNS: TableColumn<ServiceRequestSummary>[] = [
  SERVICE_REQUEST_TITLE_COLUMN,
  SERVICE_REQUEST_TAGS_COLUMN,
  SERVICE_REQUEST_STATUS_COLUMN,
  SERVICE_REQUEST_ASSIGNEE_COLUMN,
  SERVICE_REQUEST_CREATED_COLUMN,
];

interface MyServiceRequestsTableProps {
  serviceRequests: ServiceRequestSummary[];
}

export function MyServiceRequestsTable({ serviceRequests }: MyServiceRequestsTableProps) {
  return (
    <Table
      columns={MY_SERVICE_REQUEST_COLUMNS}
      rows={serviceRequests}
      rowKey={(serviceRequest) => serviceRequest.id}
      caption={ServiceRequestMessage.TableCaption}
      emptyTitle={ServiceRequestMessage.EmptyTitle}
      emptyDescription={ServiceRequestMessage.EmptyDescription}
      emptyAction={
        <Link href={AppRoute.NewServiceRequest} className={getButtonClassNames()}>
          {ServiceRequestMessage.NewRequest}
        </Link>
      }
    />
  );
}
