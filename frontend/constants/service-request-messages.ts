// Text for the My requests page, the new request form and the request detail page with both history timelines.
// Also formats the history headings and field hints, and builds the tag options of the new request form.

import type { SelectOption } from "@/components/shared/select";
import {
  SERVICE_REQUEST_STATUS_LABELS,
  SERVICE_REQUEST_TAG_LABELS,
} from "@/constants/enum-labels";
import type { ServiceRequestStatus } from "@/enums/service-request-status";
import { ServiceRequestTag } from "@/enums/service-request-tag";

export const ServiceRequestMessage = {
  MyRequestsTitle: "My requests",
  MyRequestsDescription: "Every service request you have raised, with its current status.",
  ListTitle: "Your requests",
  ListDescription: "Newest requests first. Open a request to see its full history.",
  TableCaption: "My service requests",
  TitleColumn: "Title",
  TagsColumn: "Tags",
  StatusColumn: "Status",
  AssigneeColumn: "Assignee",
  CreatedColumn: "Created",
  EmptyTitle: "No requests yet",
  EmptyDescription: "Raise a request when you need help from the IT Support team.",
  RequestsLoadFailed: "Your requests could not be loaded.",
  NewRequest: "New request",
  NewRequestTitle: "New request",
  NewRequestDescription: "Describe what you need and the IT Support team will pick it up.",
  FormTitle: "Request details",
  FormDescription: "Give the request a short title, describe the problem and choose the matching tags.",
  TitleLabel: "Title",
  TitlePlaceholder: "For example, laptop cannot connect to the office Wi-Fi",
  DescriptionLabel: "Description",
  DescriptionPlaceholder: "Include what happened, when it started and anything you have already tried.",
  TagsLegend: "Tags",
  TagsHint: "Choose every tag that applies.",
  TagsRequired: "Select at least one tag.",
  Submit: "Submit request",
  Cancel: "Cancel",
  RequestCreated: "Your request has been submitted.",
  DetailTitle: "Request details",
  DetailDescription: "Review this request together with its status and assignee history.",
  TagsLabel: "Tags",
  RequestedByLabel: "Requested by",
  AssigneeLabel: "Assignee",
  CreatedLabel: "Created",
  Unassigned: "Unassigned",
  StatusHistoryTitle: "Status history",
  StatusHistoryDescription: "Every status this request has moved through, newest first.",
  StatusHistoryEmptyTitle: "No status changes",
  StatusHistoryEmptyDescription: "The status of this request has not changed yet.",
  InitialStatusMeta: "Recorded when the request was created",
  AssigneeHistoryTitle: "Assignee history",
  AssigneeHistoryDescription: "Everyone this request has been assigned to, newest first.",
  AssigneeHistoryEmptyTitle: "Not assigned yet",
  AssigneeHistoryEmptyDescription: "This request is waiting for a member of IT Support to pick it up.",
  RemarkLabel: "Remark",
  RequestLoadFailed: "This request could not be loaded.",
  NotFoundTitle: "Request not found",
  NotFoundDescription: "This request does not exist or you do not have access to it.",
  BackToMyRequests: "Back to my requests",
} as const;

export function formatStatusChange(
  fromStatus: ServiceRequestStatus,
  toStatus: ServiceRequestStatus,
): string {
  return `Changed from ${SERVICE_REQUEST_STATUS_LABELS[fromStatus]} to ${SERVICE_REQUEST_STATUS_LABELS[toStatus]}`;
}

export function formatInitialStatus(status: ServiceRequestStatus): string {
  return `Created with status ${SERVICE_REQUEST_STATUS_LABELS[status]}`;
}

export function formatAssignment(assigneeName: string): string {
  return `Assigned to ${assigneeName}`;
}

export function formatReassignment(previousAssigneeName: string, nextAssigneeName: string): string {
  return `Reassigned from ${previousAssigneeName} to ${nextAssigneeName}`;
}

export function formatChangedBy(userName: string): string {
  return `By ${userName}`;
}

export function formatCharacterLimit(maximum: number): string {
  return `Up to ${maximum} characters.`;
}

export function formatRequestReference(serviceRequestId: number): string {
  return `#${serviceRequestId}`;
}

export const SERVICE_REQUEST_TAG_OPTIONS: SelectOption<ServiceRequestTag>[] = Object.values(
  ServiceRequestTag,
).map((tag) => ({
  value: tag,
  label: SERVICE_REQUEST_TAG_LABELS[tag],
}));
