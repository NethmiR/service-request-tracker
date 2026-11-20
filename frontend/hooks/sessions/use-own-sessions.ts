// Loads the signed in user's active sessions and ends one or all other sessions.
// The list is reloaded from the API after every change so the backend stays the source of truth.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";
import { sessionService } from "@/services/sessions/session-service";
import type { UserSession } from "@/types/sessions/user-session";

export type OwnSessionsState =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; sessions: UserSession[] }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface OwnSessions {
  state: OwnSessionsState;
  reload: () => Promise<void>;
  clearSession: (session: UserSession) => Promise<void>;
  clearOtherSessions: () => Promise<void>;
}

const LOADING_STATE: OwnSessionsState = { status: LoadStatus.Loading };

async function fetchSessionsState(): Promise<OwnSessionsState> {
  try {
    const sessions = await sessionService.listOwnSessions();

    return { status: LoadStatus.Loaded, sessions };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function useOwnSessions(): OwnSessions {
  const [state, setState] = useState<OwnSessionsState>(LOADING_STATE);
  const latestRequestRef = useRef(0);

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchSessionsState();

    if (requestNumber === latestRequestRef.current) {
      setState(nextState);
    }
  }, []);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchSessionsState().then((nextState) => {
      if (requestNumber === requests.current) {
        setState(nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, []);

  const clearSession = useCallback(
    async (session: UserSession) => {
      await sessionService.clearSession(session.id);
      await reload();
    },
    [reload],
  );

  const clearOtherSessions = useCallback(async () => {
    await sessionService.clearOtherSessions();
    await reload();
  }, [reload]);

  return useMemo(
    () => ({ state, reload, clearSession, clearOtherSessions }),
    [state, reload, clearSession, clearOtherSessions],
  );
}
