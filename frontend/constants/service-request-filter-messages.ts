import type { SelectOption } from "@/components/shared/select";
import { SERVICE_REQUEST_STATUS_LABELS } from "@/constants/enum-labels";
import { ALL_FILTER_VALUE } from "@/constants/service-request-filter-fields";
import { ServiceRequestStatus } from "@/enums/service-request-status";

export const ServiceRequestFilterMessage = {
  FiltersLabel: "Filter requests",
  StatusLabel: "Status",
  AllStatuses: "All statuses",
  AssigneeLabel: "Assignee",
  AllAssignees: "All assignees",
  AssigneesLoading: "Loading assignees",
  AssigneesUnavailable: "Assignees could not be loaded",
  RetryAssignees: "Reload assignees",
  ClearFilters: "Clear filters",
  EmptyTitle: "No matching requests",
  EmptyDescription: "No request matches the selected filters. Change or clear them to see more requests.",
} as const;

export type StatusFilterValue = ServiceRequestStatus | typeof ALL_FILTER_VALUE;

export const STATUS_FILTER_OPTIONS: SelectOption<StatusFilterValue>[] = [
  { value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AllStatuses },
  ...Object.values(ServiceRequestStatus).map((status) => ({
    value: status,
    label: SERVICE_REQUEST_STATUS_LABELS[status],
  })),
];
