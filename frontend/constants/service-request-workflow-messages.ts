// Text for the request dashboard, the assign to me and reassign dialogs and the status change controls.
// Also formats the workflow notifications and builds the status options from the statuses the backend allows.

import type { SelectOption } from "@/components/shared/select";
import { SERVICE_REQUEST_STATUS_LABELS } from "@/constants/enum-labels";
import type { ServiceRequestStatus } from "@/enums/service-request-status";

export const ServiceRequestWorkflowMessage = {
  DashboardTitle: "Requests",
  DashboardDescription: "Every service request raised across the company, with its current status.",
  ListTitle: "All requests",
  ListDescription: "Newest requests first. Assign an open request to yourself or open one to work on it.",
  TableCaption: "All service requests",
  EmptyTitle: "No requests yet",
  EmptyDescription: "Requests raised by anyone in the company will appear here.",
  RequestsLoadFailed: "The requests could not be loaded.",
  RequestedByColumn: "Requested by",
  ActionsColumn: "Actions",
  AssignToMe: "Assign to me",
  AssignDialogTitle: "Assign this request to yourself",
  AssignDialogDescription: "The request moves to Assigned and you become responsible for it.",
  AssignSubmit: "Assign to me",
  RemarkLabel: "Remark",
  RemarkPlaceholder: "Add a short note about this change.",
  RemarkRequiredHint: "A remark is required.",
  RemarkOptionalHint: "A remark is optional.",
  Reassign: "Reassign",
  ReassignDialogTitle: "Reassign this request",
  ReassignDialogDescription:
    "Choose the technician or admin who takes over this request. Its status stays the same.",
  AssigneeLabel: "New assignee",
  AssigneePlaceholder: "Choose a technician or admin",
  NoOtherAssignees: "There is no other technician or admin to reassign this request to.",
  AssigneesLoadFailed: "The list of technicians and admins could not be loaded.",
  ReassignSubmit: "Reassign",
  WorkSectionTitle: "Work on this request",
  WorkSectionDescription: "Take the request, hand it over or move it to its next status.",
  StatusLabel: "New status",
  StatusPlaceholder: "Choose a status",
  StatusSubmit: "Change status",
  RequestAssigned: "The request has been assigned to you.",
  BackToDashboard: "Back to requests",
} as const;

export function formatReassignedNotification(assigneeName: string): string {
  return `The request has been reassigned to ${assigneeName}.`;
}

export function formatStatusChangedNotification(status: ServiceRequestStatus): string {
  return `The request has been moved to ${SERVICE_REQUEST_STATUS_LABELS[status]}.`;
}

export function statusOptions(
  statuses: readonly ServiceRequestStatus[],
): SelectOption<ServiceRequestStatus>[] {
  return statuses.map((status) => ({
    value: status,
    label: SERVICE_REQUEST_STATUS_LABELS[status],
  }));
}
