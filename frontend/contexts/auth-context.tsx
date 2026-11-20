// Provides the signed in user, the auth status and the sign in, sign out and password actions.
// Reacts to session events from the HTTP layer and never navigates, which the route guards handle.

"use client";

import { createContext, useCallback, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { AuthMessage } from "@/constants/auth-messages";
import { ErrorCode } from "@/enums/error-code";
import { AuthStatus } from "@/enums/ui/auth-status";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import { ApiError } from "@/lib/http/api-error";
import { HttpEvent, httpEvents } from "@/lib/http/http-events";
import { authService } from "@/services/auth/auth-service";
import type { ChangePasswordRequest } from "@/types/auth/change-password-request";
import type { CurrentUser } from "@/types/auth/current-user";
import type { SignInRequest } from "@/types/auth/sign-in-request";

type AuthState =
  | { status: AuthStatus.Loading; user: null }
  | { status: AuthStatus.Authenticated; user: CurrentUser }
  | { status: AuthStatus.Unauthenticated; user: null };

export interface AuthContextValue {
  status: AuthStatus;
  user: CurrentUser | null;
  signIn: (request: SignInRequest) => Promise<CurrentUser>;
  signOut: () => Promise<void>;
  changePassword: (request: ChangePasswordRequest) => Promise<CurrentUser>;
  refreshUser: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

const LOADING_STATE: AuthState = { status: AuthStatus.Loading, user: null };

const SIGNED_OUT_STATE: AuthState = { status: AuthStatus.Unauthenticated, user: null };

function authenticatedState(user: CurrentUser): AuthState {
  return { status: AuthStatus.Authenticated, user };
}

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const { notify } = useNotification();
  const [state, setState] = useState<AuthState>(LOADING_STATE);

  useEffect(() => {
    let isActive = true;

    const keepUnlessSettled = (next: AuthState) => {
      if (isActive) {
        setState((current) => (current.status === AuthStatus.Loading ? next : current));
      }
    };

    authService
      .getCurrentUser()
      .then((user) => keepUnlessSettled(authenticatedState(user)))
      .catch(() => keepUnlessSettled(SIGNED_OUT_STATE));

    return () => {
      isActive = false;
    };
  }, []);

  useEffect(() => {
    const unsubscribeUnauthenticated = httpEvents.subscribe(HttpEvent.Unauthenticated, () => {
      setState(SIGNED_OUT_STATE);
    });

    const unsubscribePasswordChangeRequired = httpEvents.subscribe(
      HttpEvent.PasswordChangeRequired,
      () => {
        setState((current) =>
          current.status === AuthStatus.Authenticated
            ? authenticatedState({ ...current.user, password_change_required: true })
            : current,
        );
      },
    );

    return () => {
      unsubscribeUnauthenticated();
      unsubscribePasswordChangeRequired();
    };
  }, []);

  const signIn = useCallback(async (request: SignInRequest) => {
    const user = await authService.signIn(request);
    setState(authenticatedState(user));

    return user;
  }, []);

  const signOut = useCallback(async () => {
    try {
      await authService.signOut();
      setState(SIGNED_OUT_STATE);
    } catch (error) {
      if (error instanceof ApiError && error.hasCode(ErrorCode.Unauthenticated)) {
        return;
      }

      notify(NotificationType.Error, AuthMessage.SignOutFailed);
    }
  }, [notify]);

  const changePassword = useCallback(async (request: ChangePasswordRequest) => {
    const user = await authService.changePassword(request);
    setState(authenticatedState(user));

    return user;
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const user = await authService.getCurrentUser();
      setState(authenticatedState(user));
    } catch {
      setState(SIGNED_OUT_STATE);
    }
  }, []);

  const value = useMemo(
    () => ({
      status: state.status,
      user: state.user,
      signIn,
      signOut,
      changePassword,
      refreshUser,
    }),
    [state, signIn, signOut, changePassword, refreshUser],
  );

  return <AuthContext value={value}>{children}</AuthContext>;
}
