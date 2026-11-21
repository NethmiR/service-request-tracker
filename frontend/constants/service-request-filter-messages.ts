// Text of the request filter bar shared by My requests and the dashboard.
// Also builds the status and priority filter options, each starting with an option that shows every request.

import type { SelectOption } from "@/components/shared/select";
import {
  SERVICE_REQUEST_PRIORITY_LABELS,
  SERVICE_REQUEST_STATUS_LABELS,
} from "@/constants/enum-labels";
import { ALL_FILTER_VALUE } from "@/constants/service-request-filter-fields";
import { ServiceRequestPriority } from "@/enums/service-request-priority";
import { ServiceRequestStatus } from "@/enums/service-request-status";

export const ServiceRequestFilterMessage = {
  FiltersLabel: "Filter requests",
  SearchLabel: "Search by title",
  SearchPlaceholder: "Any part of the title",
  StatusLabel: "Status",
  AllStatuses: "All statuses",
  PriorityLabel: "Priority",
  AllPriorities: "All priorities",
  AssigneeLabel: "Assignee",
  AllAssignees: "All assignees",
  AssigneesLoading: "Loading assignees",
  AssigneesUnavailable: "Assignees could not be loaded",
  RetryAssignees: "Reload assignees",
  ClearFilters: "Clear filters",
  EmptyTitle: "No matching requests",
  EmptyDescription:
    "No request matches the search or the selected filters. Change or clear them to see more requests.",
} as const;

export type StatusFilterValue = ServiceRequestStatus | typeof ALL_FILTER_VALUE;

export type PriorityFilterValue = ServiceRequestPriority | typeof ALL_FILTER_VALUE;

export const STATUS_FILTER_OPTIONS: SelectOption<StatusFilterValue>[] = [
  { value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AllStatuses },
  ...Object.values(ServiceRequestStatus).map((status) => ({
    value: status,
    label: SERVICE_REQUEST_STATUS_LABELS[status],
  })),
];

export const PRIORITY_FILTER_OPTIONS: SelectOption<PriorityFilterValue>[] = [
  { value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AllPriorities },
  ...Object.values(ServiceRequestPriority).map((priority) => ({
    value: priority,
    label: SERVICE_REQUEST_PRIORITY_LABELS[priority],
  })),
];
