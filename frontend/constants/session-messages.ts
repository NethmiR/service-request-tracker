// Text for the active sessions section, its table, its confirmation dialogs and its notifications.
// Also builds the readable device label from the detected browser and platform.

export const SessionMessage = {
  SectionTitle: "Active sessions",
  SectionDescription:
    "Devices that are signed in to your account. End any session you do not recognise.",
  TableCaption: "Active sessions",
  DeviceColumn: "Device",
  IpAddressColumn: "IP address",
  LastActiveColumn: "Last active",
  ActionsColumn: "Actions",
  CurrentDevice: "This device",
  EndSession: "End session",
  EndOtherSessions: "End all other sessions",
  EndSessionTitle: "End this session?",
  EndSessionDescription:
    "The device using this session will be signed out and must sign in again.",
  EndOtherSessionsTitle: "End all other sessions?",
  EndOtherSessionsDescription:
    "Every device except this one will be signed out and must sign in again.",
  SessionEnded: "The session has been ended.",
  OtherSessionsEnded: "All other sessions have been ended.",
  SessionAlreadyEnded: "This session had already ended.",
  LoadFailed: "Your active sessions could not be loaded.",
  UnknownIpAddress: "Unknown",
  NoOtherSessions: "This is your only active session.",
  UnknownDevice: "Unknown device",
} as const;

export function formatDeviceLabel(browser: string | null, platform: string | null): string {
  if (browser !== null && platform !== null) {
    return `${browser} on ${platform}`;
  }

  return browser ?? platform ?? SessionMessage.UnknownDevice;
}
