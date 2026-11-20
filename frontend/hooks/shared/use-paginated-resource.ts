// Loads one page of any paginated resource and moves between pages.
// Keeps the previous page visible while the next one loads and ignores responses that arrive late.
// Callers pass a stable page loader so the current page is not fetched again on every render.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { FIRST_PAGE } from "@/constants/http";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

export type PaginatedResourceState<Resource> =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; page: PaginatedResponse<Resource> }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface PaginatedResource<Resource> {
  state: PaginatedResourceState<Resource>;
  page: number;
  goToPage: (page: number) => void;
  isChangingPage: boolean;
  reload: () => Promise<void>;
}

export type PageLoader<Resource> = (page: number) => Promise<PaginatedResponse<Resource>>;

async function fetchPageState<Resource>(
  loadPage: PageLoader<Resource>,
  page: number,
): Promise<PaginatedResourceState<Resource>> {
  try {
    const response = await loadPage(page);

    return { status: LoadStatus.Loaded, page: response };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function usePaginatedResource<Resource>(
  loadPage: PageLoader<Resource>,
): PaginatedResource<Resource> {
  const [state, setState] = useState<PaginatedResourceState<Resource>>({
    status: LoadStatus.Loading,
  });
  const [page, setPage] = useState(FIRST_PAGE);
  const latestRequestRef = useRef(0);

  const applyState = useCallback(
    (requestedPage: number, nextState: PaginatedResourceState<Resource>) => {
      const isEmptyLaterPage =
        nextState.status === LoadStatus.Loaded &&
        nextState.page.data.length === 0 &&
        requestedPage > FIRST_PAGE;

      if (isEmptyLaterPage) {
        setPage(Math.max(nextState.page.meta.last_page, FIRST_PAGE));
        return;
      }

      setState(nextState);
    },
    [],
  );

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchPageState(loadPage, page);

    if (requestNumber === latestRequestRef.current) {
      applyState(page, nextState);
    }
  }, [loadPage, page, applyState]);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchPageState(loadPage, page).then((nextState) => {
      if (requestNumber === requests.current) {
        applyState(page, nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, [loadPage, page, applyState]);

  const goToPage = useCallback((nextPage: number) => {
    setPage(Math.max(nextPage, FIRST_PAGE));
  }, []);

  const isChangingPage =
    state.status === LoadStatus.Loaded && state.page.meta.current_page !== page;

  return useMemo(
    () => ({ state, page, goToPage, isChangingPage, reload }),
    [state, page, goToPage, isChangingPage, reload],
  );
}
