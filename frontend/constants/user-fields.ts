// Field names of the create user form.
// Typed against the request type, so a renamed field fails the type check.

import type { CreateUserRequest } from "@/types/users/create-user-request";

export const CreateUserField = {
  Name: "name",
  Email: "email",
  Role: "role",
} as const satisfies Record<string, keyof CreateUserRequest>;

export type CreateUserFieldName = (typeof CreateUserField)[keyof typeof CreateUserField];

export const CREATE_USER_FIELDS: readonly CreateUserFieldName[] = Object.values(CreateUserField);
