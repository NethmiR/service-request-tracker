// Loads one page of users for the admin users list and moves between pages.
// Passes the users endpoint to the shared paginated resource hook.

"use client";

import {
  usePaginatedResource,
  type PaginatedResource,
} from "@/hooks/shared/use-paginated-resource";
import { userService } from "@/services/users/user-service";
import type { PaginatedResponse } from "@/types/shared/paginated-response";
import type { ManagedUser } from "@/types/users/managed-user";

function loadUsersPage(page: number): Promise<PaginatedResponse<ManagedUser>> {
  return userService.listUsers(page);
}

export function useUsers(): PaginatedResource<ManagedUser> {
  return usePaginatedResource(loadUsersPage);
}
