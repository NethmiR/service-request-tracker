// Text for the sessions section, its table, its confirmation dialogs and its notifications.
// Also holds the per screen copy for the account and user detail pages and builds the readable device label.

import type { SessionSectionCopy } from "@/types/sessions/session-section-copy";

export const SessionMessage = {
  TableCaption: "Active sessions",
  DeviceColumn: "Device",
  IpAddressColumn: "IP address",
  LastActiveColumn: "Last active",
  ActionsColumn: "Actions",
  CurrentDevice: "This device",
  EndSession: "End session",
  EndSessionTitle: "End this session?",
  EndSessionDescription:
    "The device using this session will be signed out and must sign in again.",
  SessionEnded: "The session has been ended.",
  SessionAlreadyEnded: "This session had already ended.",
  UnknownIpAddress: "Unknown",
  UnknownDevice: "Unknown device",
} as const;

export const OWN_SESSIONS_COPY: SessionSectionCopy = {
  title: "Active sessions",
  description:
    "Devices that are signed in to your account. End any session you do not recognise.",
  endAllLabel: "End all other sessions",
  endAllTitle: "End all other sessions?",
  endAllDescription: "Every device except this one will be signed out and must sign in again.",
  endAllSucceeded: "All other sessions have been ended.",
  nothingToEnd: "This is your only active session.",
  loadFailed: "Your active sessions could not be loaded.",
};

export const USER_SESSIONS_COPY: SessionSectionCopy = {
  title: "Active sessions",
  description: "Devices where this user is signed in. Ending a session signs that device out.",
  endAllLabel: "End all sessions",
  endAllTitle: "End all sessions of this user?",
  endAllDescription:
    "Every device of this user will be signed out. Your own current session is never ended here.",
  endAllSucceeded: "All sessions of this user have been ended.",
  nothingToEnd: "This user has no other active sessions.",
  loadFailed: "The sessions of this user could not be loaded.",
};

export function formatDeviceLabel(browser: string | null, platform: string | null): string {
  if (browser !== null && platform !== null) {
    return `${browser} on ${platform}`;
  }

  return browser ?? platform ?? SessionMessage.UnknownDevice;
}
