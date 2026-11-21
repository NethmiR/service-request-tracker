// Field names, the empty filter set, the all option value and the search delay of the request filter bar.
// The field names are typed against the filter type, so a renamed field fails the type check.

import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";

export const ServiceRequestFilterField = {
  Status: "status",
  AssigneeId: "assignee_id",
  Priority: "priority",
  Search: "search",
} as const satisfies Record<string, keyof ServiceRequestFilters>;

export const NO_SERVICE_REQUEST_FILTERS: Readonly<ServiceRequestFilters> = {};

export const ALL_FILTER_VALUE = "";

export const SEARCH_DEBOUNCE_MILLISECONDS = 400;
