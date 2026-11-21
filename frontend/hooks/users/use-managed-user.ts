// Loads one user by id for the admin user detail page.
// Passes a loader keyed by the user id to the shared single resource hook.

"use client";

import { useCallback } from "react";
import { useResource, type ResourceResult } from "@/hooks/shared/use-resource";
import { userService } from "@/services/users/user-service";
import type { ManagedUser } from "@/types/users/managed-user";

export function useManagedUser(userId: number): ResourceResult<ManagedUser> {
  const loadUser = useCallback(() => userService.getUser(userId), [userId]);

  return useResource(loadUser);
}
