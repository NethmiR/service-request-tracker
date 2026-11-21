// Filter bar shared by My requests and the dashboard with a title search and the status, priority and assignee filters.
// The search box keeps its own text and updates the filters only after typing pauses, while the selects apply at once.

"use client";

import { useEffect, useState } from "react";
import type { ChangeEvent } from "react";
import { Button } from "@/components/shared/button";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { Select, type SelectOption } from "@/components/shared/select";
import {
  ALL_FILTER_VALUE,
  NO_SERVICE_REQUEST_FILTERS,
  SEARCH_DEBOUNCE_MILLISECONDS,
  ServiceRequestFilterField,
} from "@/constants/service-request-filter-fields";
import {
  PRIORITY_FILTER_OPTIONS,
  STATUS_FILTER_OPTIONS,
  ServiceRequestFilterMessage,
} from "@/constants/service-request-filter-messages";
import { SERVICE_REQUEST_TITLE_MAX_LENGTH } from "@/constants/service-request-limits";
import { AutocompleteToken } from "@/enums/ui/autocomplete-token";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { LoadStatus } from "@/enums/ui/load-status";
import { useDebouncedValue } from "@/hooks/shared/use-debounced-value";
import type { ResourceResult, ResourceState } from "@/hooks/shared/use-resource";
import {
  assigneeFilterValue,
  hasActiveFilters,
  parseAssigneeFilter,
  parsePriorityFilter,
  parseSearchFilter,
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
  const [searchText, setSearchText] = useState(filters.search ?? "");
  const debouncedSearchText = useDebouncedValue(searchText, SEARCH_DEBOUNCE_MILLISECONDS);

  useEffect(() => {
    const hasSettled = debouncedSearchText === searchText;
    const search = parseSearchFilter(debouncedSearchText);

    if (hasSettled && search !== filters.search) {
      onChange({ ...filters, search });
    }
  }, [debouncedSearchText, searchText, filters, onChange]);

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

  function handlePriorityChange(event: ChangeEvent<HTMLSelectElement>) {
    onChange({ ...filters, priority: parsePriorityFilter(event.target.value) });
  }

  function handleAssigneeChange(event: ChangeEvent<HTMLSelectElement>) {
    onChange({ ...filters, assignee_id: parseAssigneeFilter(event.target.value) });
  }

  function handleClearFilters() {
    setSearchText("");
    onChange(NO_SERVICE_REQUEST_FILTERS);
  }

  return (
    <div
      role="group"
      aria-label={ServiceRequestFilterMessage.FiltersLabel}
      className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
    >
      <FormField label={ServiceRequestFilterMessage.SearchLabel} className="sm:min-w-64 sm:flex-1">
        {(controlProps) => (
          <Input
            {...controlProps}
            type="search"
            name={ServiceRequestFilterField.Search}
            value={searchText}
            maxLength={SERVICE_REQUEST_TITLE_MAX_LENGTH}
            placeholder={ServiceRequestFilterMessage.SearchPlaceholder}
            autoComplete={AutocompleteToken.Off}
            onChange={(event: ChangeEvent<HTMLInputElement>) => setSearchText(event.target.value)}
          />
        )}
      </FormField>
      <FormField label={ServiceRequestFilterMessage.StatusLabel} className="sm:w-48">
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
      <FormField label={ServiceRequestFilterMessage.PriorityLabel} className="sm:w-48">
        {(controlProps) => (
          <Select
            {...controlProps}
            name={ServiceRequestFilterField.Priority}
            options={PRIORITY_FILTER_OPTIONS}
            value={filters.priority ?? ALL_FILTER_VALUE}
            onChange={handlePriorityChange}
          />
        )}
      </FormField>
      <FormField label={ServiceRequestFilterMessage.AssigneeLabel} className="sm:w-48">
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
      {(hasActiveFilters(filters) || searchText !== "") && (
        <Button
          variant={ButtonVariant.Ghost}
          size={ComponentSize.Small}
          className="self-start sm:mb-1 sm:self-auto"
          onClick={handleClearFilters}
        >
          {ServiceRequestFilterMessage.ClearFilters}
        </Button>
      )}
    </div>
  );
}
