// Messages for programming and configuration mistakes that developers must fix.
// They are thrown as errors and are never part of the normal UI text.

export const DeveloperMessage = {
  MissingApiUrl:
    "NEXT_PUBLIC_API_URL is not set. Add it to the frontend .env file, for example NEXT_PUBLIC_API_URL=http://localhost:8000.",
  NotificationProviderMissing:
    "useNotification must be used inside NotificationProvider.",
} as const;
