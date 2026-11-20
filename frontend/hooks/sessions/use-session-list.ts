// Loads a list of active sessions from a given source and ends one session or every session that can be ended.
// The list is reloaded from the API after every change so the backend stays the source of truth.

"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { LoadStatus } from "@/enums/ui/load-status";
import { ApiError } from "@/lib/http/api-error";
import type { SessionListSource } from "@/types/sessions/session-list-source";
import type { UserSession } from "@/types/sessions/user-session";

export type SessionListState =
  | { status: LoadStatus.Loading }
  | { status: LoadStatus.Loaded; sessions: UserSession[] }
  | { status: LoadStatus.Failed; error: ApiError | null };

export interface SessionList {
  state: SessionListState;
  reload: () => Promise<void>;
  clearSession: (session: UserSession) => Promise<void>;
  clearSessions: () => Promise<void>;
}

const LOADING_STATE: SessionListState = { status: LoadStatus.Loading };

async function fetchSessionsState(source: SessionListSource): Promise<SessionListState> {
  try {
    const sessions = await source.listSessions();

    return { status: LoadStatus.Loaded, sessions };
  } catch (error) {
    return { status: LoadStatus.Failed, error: error instanceof ApiError ? error : null };
  }
}

export function useSessionList(source: SessionListSource): SessionList {
  const [state, setState] = useState<SessionListState>(LOADING_STATE);
  const latestRequestRef = useRef(0);

  const reload = useCallback(async () => {
    latestRequestRef.current += 1;
    const requestNumber = latestRequestRef.current;
    const nextState = await fetchSessionsState(source);

    if (requestNumber === latestRequestRef.current) {
      setState(nextState);
    }
  }, [source]);

  useEffect(() => {
    const requests = latestRequestRef;
    requests.current += 1;
    const requestNumber = requests.current;

    void fetchSessionsState(source).then((nextState) => {
      if (requestNumber === requests.current) {
        setState(nextState);
      }
    });

    return () => {
      requests.current += 1;
    };
  }, [source]);

  const clearSession = useCallback(
    async (session: UserSession) => {
      await source.clearSession(session.id);
      await reload();
    },
    [source, reload],
  );

  const clearSessions = useCallback(async () => {
    await source.clearSessions();
    await reload();
  }, [source, reload]);

  return useMemo(
    () => ({ state, reload, clearSession, clearSessions }),
    [state, reload, clearSession, clearSessions],
  );
}
