import { ALL_FILTER_VALUE } from "@/constants/service-request-filter-fields";
import { ServiceRequestStatus } from "@/enums/service-request-status";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";

export function hasActiveFilters(filters: ServiceRequestFilters): boolean {
  return filters.status !== undefined || filters.assignee_id !== undefined;
}

export function parseStatusFilter(value: string): ServiceRequestStatus | undefined {
  return Object.values(ServiceRequestStatus).find((status) => status === value);
}

export function parseAssigneeFilter(value: string): number | undefined {
  const assigneeId = Number(value);

  return value !== ALL_FILTER_VALUE && Number.isSafeInteger(assigneeId) && assigneeId > 0
    ? assigneeId
    : undefined;
}

export function assigneeFilterValue(assigneeId: number | undefined): string {
  return assigneeId === undefined ? ALL_FILTER_VALUE : String(assigneeId);
}
