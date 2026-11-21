// Loads one record of any resource and reloads it on demand while ignoring responses that arrive late.
// A not found error stays in the failed state so the page can show its own not found message.
// Callers pass a stable loader, and a record returned by a write can replace the loaded one without a new fetch.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";

export type ResourceState<Resource> =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; resource: Resource }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface ResourceResult<Resource> {
  state: ResourceState<Resource>;
  reload: () => Promise<void>;
  replace: (resource: Resource) => void;
}

export type ResourceLoader<Resource> = () => Promise<Resource>;

async function fetchResourceState<Resource>(
  load: ResourceLoader<Resource>,
): Promise<ResourceState<Resource>> {
  try {
    const resource = await load();

    return { status: LoadStatus.Loaded, resource };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function useResource<Resource>(load: ResourceLoader<Resource>): ResourceResult<Resource> {
  const [state, setState] = useState<ResourceState<Resource>>({ status: LoadStatus.Loading });
  const latestRequestRef = useRef(0);

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchResourceState(load);

    if (requestNumber === latestRequestRef.current) {
      setState(nextState);
    }
  }, [load]);

  const replace = useCallback((resource: Resource) => {
    latestRequestRef.current += 1;
    setState({ status: LoadStatus.Loaded, resource });
  }, []);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchResourceState(load).then((nextState) => {
      if (requestNumber === requests.current) {
        setState(nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, [load]);

  return useMemo(() => ({ state, reload, replace }), [state, reload, replace]);
}
