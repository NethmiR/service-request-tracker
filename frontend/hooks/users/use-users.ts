// Loads one page of users for the admin users list and moves between pages.
// Keeps the previous page visible while the next one loads and ignores responses that arrive late.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";
import { userService } from "@/services/users/user-service";
import type { PaginatedResponse } from "@/types/shared/paginated-response";
import type { ManagedUser } from "@/types/users/managed-user";

export type UsersState =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; users: PaginatedResponse<ManagedUser> }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface UsersList {
  state: UsersState;
  page: number;
  goToPage: (page: number) => void;
  isChangingPage: boolean;
  reload: () => Promise<void>;
}

export const FIRST_USERS_PAGE = 1;

const LOADING_STATE: UsersState = { status: LoadStatus.Loading };

async function fetchUsersState(page: number): Promise<UsersState> {
  try {
    const users = await userService.listUsers(page);

    return { status: LoadStatus.Loaded, users };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function useUsers(): UsersList {
  const [state, setState] = useState<UsersState>(LOADING_STATE);
  const [page, setPage] = useState(FIRST_USERS_PAGE);
  const latestRequestRef = useRef(0);

  const applyState = useCallback((requestedPage: number, nextState: UsersState) => {
    const isEmptyLaterPage =
      nextState.status === LoadStatus.Loaded &&
      nextState.users.data.length === 0 &&
      requestedPage > FIRST_USERS_PAGE;

    if (isEmptyLaterPage) {
      setPage(Math.max(nextState.users.meta.last_page, FIRST_USERS_PAGE));
      return;
    }

    setState(nextState);
  }, []);

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchUsersState(page);

    if (requestNumber === latestRequestRef.current) {
      applyState(page, nextState);
    }
  }, [page, applyState]);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchUsersState(page).then((nextState) => {
      if (requestNumber === requests.current) {
        applyState(page, nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, [page, applyState]);

  const goToPage = useCallback((nextPage: number) => {
    setPage(Math.max(nextPage, FIRST_USERS_PAGE));
  }, []);

  const isChangingPage =
    state.status === LoadStatus.Loaded && state.users.meta.current_page !== page;

  return useMemo(
    () => ({ state, page, goToPage, isChangingPage, reload }),
    [state, page, goToPage, isChangingPage, reload],
  );
}
