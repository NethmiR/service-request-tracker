// Presentational table of every service request for the dashboard, with requester, tags, status, priority, assignee and creation time.
// Rows the backend marks as assignable to the viewer show an assign to me button, and the table holds no data loading.

import {
  SERVICE_REQUEST_ASSIGNEE_COLUMN,
  SERVICE_REQUEST_CREATED_COLUMN,
  SERVICE_REQUEST_PRIORITY_COLUMN,
  SERVICE_REQUEST_REQUESTED_BY_COLUMN,
  SERVICE_REQUEST_STATUS_COLUMN,
  SERVICE_REQUEST_TAGS_COLUMN,
  SERVICE_REQUEST_TITLE_COLUMN,
} from "@/components/service-requests/service-request-columns";
import { Button } from "@/components/shared/button";
import { Table, type TableColumn } from "@/components/shared/table";
import { ServiceRequestFilterMessage } from "@/constants/service-request-filter-messages";
import { ServiceRequestWorkflowMessage } from "@/constants/service-request-workflow-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { ServiceRequestTableColumn } from "@/enums/ui/service-request-table-column";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";

function createAssignColumn(
  onAssign: (serviceRequest: ServiceRequestSummary) => void,
): TableColumn<ServiceRequestSummary> {
  return {
    key: ServiceRequestTableColumn.Actions,
    header: <span className="sr-only">{ServiceRequestWorkflowMessage.ActionsColumn}</span>,
    className: "text-right",
    cell: (serviceRequest) =>
      serviceRequest.available_actions.can_assign_to_self ? (
        <Button
          variant={ButtonVariant.Secondary}
          size={ComponentSize.Small}
          onClick={() => onAssign(serviceRequest)}
        >
          {ServiceRequestWorkflowMessage.AssignToMe}
        </Button>
      ) : null,
  };
}

interface ServiceRequestsTableProps {
  serviceRequests: ServiceRequestSummary[];
  isFiltered: boolean;
  onAssign: (serviceRequest: ServiceRequestSummary) => void;
}

export function ServiceRequestsTable({
  serviceRequests,
  isFiltered,
  onAssign,
}: ServiceRequestsTableProps) {
  const columns: TableColumn<ServiceRequestSummary>[] = [
    SERVICE_REQUEST_TITLE_COLUMN,
    SERVICE_REQUEST_REQUESTED_BY_COLUMN,
    SERVICE_REQUEST_TAGS_COLUMN,
    SERVICE_REQUEST_STATUS_COLUMN,
    SERVICE_REQUEST_PRIORITY_COLUMN,
    SERVICE_REQUEST_ASSIGNEE_COLUMN,
    SERVICE_REQUEST_CREATED_COLUMN,
    createAssignColumn(onAssign),
  ];

  return (
    <Table
      columns={columns}
      rows={serviceRequests}
      rowKey={(serviceRequest) => serviceRequest.id}
      caption={ServiceRequestWorkflowMessage.TableCaption}
      emptyTitle={
        isFiltered
          ? ServiceRequestFilterMessage.EmptyTitle
          : ServiceRequestWorkflowMessage.EmptyTitle
      }
      emptyDescription={
        isFiltered
          ? ServiceRequestFilterMessage.EmptyDescription
          : ServiceRequestWorkflowMessage.EmptyDescription
      }
    />
  );
}
