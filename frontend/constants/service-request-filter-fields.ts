import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";

export const ServiceRequestFilterField = {
  Status: "status",
  AssigneeId: "assignee_id",
} as const satisfies Record<string, keyof ServiceRequestFilters>;

export const NO_SERVICE_REQUEST_FILTERS: Readonly<ServiceRequestFilters> = {};

export const ALL_FILTER_VALUE = "";
