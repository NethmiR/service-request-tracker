// Helpers that turn the raw filter bar values into typed request filters and back.
// Values that are not valid become undefined, so the filter is simply left out of the query.

import { ALL_FILTER_VALUE } from "@/constants/service-request-filter-fields";
import type { ServiceRequestPriority } from "@/enums/service-request-priority";
import { ServiceRequestStatus } from "@/enums/service-request-status";
import { parseServiceRequestPriority } from "@/lib/service-requests/parse-service-request-priority";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";

export function hasActiveFilters(filters: ServiceRequestFilters): boolean {
  return (
    filters.status !== undefined ||
    filters.assignee_id !== undefined ||
    filters.priority !== undefined ||
    filters.search !== undefined
  );
}

export function parseStatusFilter(value: string): ServiceRequestStatus | undefined {
  return Object.values(ServiceRequestStatus).find((status) => status === value);
}

export function parsePriorityFilter(value: string): ServiceRequestPriority | undefined {
  return parseServiceRequestPriority(value);
}

export function parseSearchFilter(value: string): string | undefined {
  const search = value.trim();

  return search === "" ? undefined : search;
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
