// Loads one user by id for the admin user detail page.
// A not found error stays in the failed state so the page can show the not found message.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";
import { userService } from "@/services/users/user-service";
import type { ManagedUser } from "@/types/users/managed-user";

export type ManagedUserState =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; user: ManagedUser }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface ManagedUserResult {
  state: ManagedUserState;
  reload: () => Promise<void>;
}

const LOADING_STATE: ManagedUserState = { status: LoadStatus.Loading };

async function fetchUserState(userId: number): Promise<ManagedUserState> {
  try {
    const user = await userService.getUser(userId);

    return { status: LoadStatus.Loaded, user };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function useManagedUser(userId: number): ManagedUserResult {
  const [state, setState] = useState<ManagedUserState>(LOADING_STATE);
  const latestRequestRef = useRef(0);

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchUserState(userId);

    if (requestNumber === latestRequestRef.current) {
      setState(nextState);
    }
  }, [userId]);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchUserState(userId).then((nextState) => {
      if (requestNumber === requests.current) {
        setState(nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, [userId]);

  return useMemo(() => ({ state, reload }), [state, reload]);
}
