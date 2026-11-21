"use client";

import { useState } from "react";
import type { ChangeEvent } from "react";
import { Button } from "@/components/shared/button";
import { FormField } from "@/components/shared/form-field";
import { Select, type SelectOption } from "@/components/shared/select";
import {
  ALL_FILTER_VALUE,
  NO_SERVICE_REQUEST_FILTERS,
  ServiceRequestFilterField,
} from "@/constants/service-request-filter-fields";
import {
  STATUS_FILTER_OPTIONS,
  ServiceRequestFilterMessage,
} from "@/constants/service-request-filter-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import type { ResourceResult, ResourceState } from "@/hooks/shared/use-resource";
import {
  assigneeFilterValue,
  hasActiveFilters,
  parseAssigneeFilter,
  parseStatusFilter,
} from "@/lib/service-requests/service-request-filters";
import type { ServiceRequestFilters } from "@/types/service-requests/service-request-filters";
import type { UserReference } from "@/types/shared/user-reference";

function assigneeOptions(assignees: ResourceState<UserReference[]>): SelectOption<string>[] {
  if (assignees.status === LoadStatus.Loading) {
    return [{ value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AssigneesLoading }];
  }

  if (assignees.status === LoadStatus.Failed) {
    return [{ value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AssigneesUnavailable }];
  }

  return [
    { value: ALL_FILTER_VALUE, label: ServiceRequestFilterMessage.AllAssignees },
    ...assignees.resource.map((assignee) => ({
      value: String(assignee.id),
      label: assignee.name,
    })),
  ];
}

interface ServiceRequestFilterBarProps {
  filters: ServiceRequestFilters;
  assignees: ResourceResult<UserReference[]>;
  onChange: (filters: ServiceRequestFilters) => void;
}

export function ServiceRequestFilterBar({
  filters,
  assignees,
  onChange,
}: ServiceRequestFilterBarProps) {
  const [isReloadingAssignees, setIsReloadingAssignees] = useState(false);

  async function handleAssigneesRetry() {
    setIsReloadingAssignees(true);

    try {
      await assignees.reload();
    } finally {
      setIsReloadingAssignees(false);
    }
  }

  function handleStatusChange(event: ChangeEvent<HTMLSelectElement>) {
    onChange({ ...filters, status: parseStatusFilter(event.target.value) });
  }

  function handleAssigneeChange(event: ChangeEvent<HTMLSelectElement>) {
    onChange({ ...filters, assignee_id: parseAssigneeFilter(event.target.value) });
  }

  return (
    <div
      role="group"
      aria-label={ServiceRequestFilterMessage.FiltersLabel}
      className="flex flex-col gap-3 sm:flex-row sm:items-end"
    >
      <FormField label={ServiceRequestFilterMessage.StatusLabel} className="sm:w-56">
        {(controlProps) => (
          <Select
            {...controlProps}
            name={ServiceRequestFilterField.Status}
            options={STATUS_FILTER_OPTIONS}
            value={filters.status ?? ALL_FILTER_VALUE}
            onChange={handleStatusChange}
          />
        )}
      </FormField>
      <FormField label={ServiceRequestFilterMessage.AssigneeLabel} className="sm:w-56">
        {(controlProps) => (
          <Select
            {...controlProps}
            name={ServiceRequestFilterField.AssigneeId}
            options={assigneeOptions(assignees.state)}
            value={assigneeFilterValue(filters.assignee_id)}
            onChange={handleAssigneeChange}
            disabled={assignees.state.status !== LoadStatus.Loaded}
          />
        )}
      </FormField>
      {assignees.state.status === LoadStatus.Failed && (
        <Button
          variant={ButtonVariant.Secondary}
          size={ComponentSize.Small}
          className="self-start sm:mb-1 sm:self-auto"
          isLoading={isReloadingAssignees}
          onClick={() => void handleAssigneesRetry()}
        >
          {ServiceRequestFilterMessage.RetryAssignees}
        </Button>
      )}
      {hasActiveFilters(filters) && (
        <Button
          variant={ButtonVariant.Ghost}
          size={ComponentSize.Small}
          className="self-start sm:mb-1 sm:self-auto"
          onClick={() => onChange(NO_SERVICE_REQUEST_FILTERS)}
        >
          {ServiceRequestFilterMessage.ClearFilters}
        </Button>
      )}
    </div>
  );
}
