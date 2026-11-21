// Field names of the assign to me, reassign and change status forms.
// Typed against the request types, so a renamed field fails the type check.

import type { AssignServiceRequestRequest } from "@/types/service-requests/assign-service-request-request";
import type { ChangeServiceRequestStatusRequest } from "@/types/service-requests/change-service-request-status-request";
import type { ReassignServiceRequestRequest } from "@/types/service-requests/reassign-service-request-request";

export const AssignServiceRequestField = {
  Remark: "remark",
} as const satisfies Record<string, keyof AssignServiceRequestRequest>;

export type AssignServiceRequestFieldName =
  (typeof AssignServiceRequestField)[keyof typeof AssignServiceRequestField];

export const ASSIGN_SERVICE_REQUEST_FIELDS: readonly AssignServiceRequestFieldName[] =
  Object.values(AssignServiceRequestField);

export const ReassignServiceRequestField = {
  AssigneeId: "assignee_id",
  Remark: "remark",
} as const satisfies Record<string, keyof ReassignServiceRequestRequest>;

export type ReassignServiceRequestFieldName =
  (typeof ReassignServiceRequestField)[keyof typeof ReassignServiceRequestField];

export const REASSIGN_SERVICE_REQUEST_FIELDS: readonly ReassignServiceRequestFieldName[] =
  Object.values(ReassignServiceRequestField);

export const ChangeServiceRequestStatusField = {
  Status: "status",
  Remark: "remark",
} as const satisfies Record<string, keyof ChangeServiceRequestStatusRequest>;

export type ChangeServiceRequestStatusFieldName =
  (typeof ChangeServiceRequestStatusField)[keyof typeof ChangeServiceRequestStatusField];

export const CHANGE_SERVICE_REQUEST_STATUS_FIELDS: readonly ChangeServiceRequestStatusFieldName[] =
  Object.values(ChangeServiceRequestStatusField);
