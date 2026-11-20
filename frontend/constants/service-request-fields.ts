// Field names of the new service request form.
// Typed against the request type, so a renamed field fails the type check.

import type { CreateServiceRequestRequest } from "@/types/service-requests/create-service-request-request";

export const CreateServiceRequestField = {
  Title: "title",
  Description: "description",
  Tags: "tags",
} as const satisfies Record<string, keyof CreateServiceRequestRequest>;

export type CreateServiceRequestFieldName =
  (typeof CreateServiceRequestField)[keyof typeof CreateServiceRequestField];

export const CREATE_SERVICE_REQUEST_FIELDS: readonly CreateServiceRequestFieldName[] =
  Object.values(CreateServiceRequestField);
