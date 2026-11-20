// General UI text shared across features.
// Also formats the page indicator and the rate limit message that any feature can show.

export const UiMessage = {
  Loading: "Loading",
  EmptyTitle: "Nothing to show yet",
  EmptyDescription: "There are no items to display.",
  Retry: "Try again",
  Close: "Close",
  Cancel: "Cancel",
  Confirm: "Confirm",
  DismissNotification: "Dismiss notification",
  NotificationsRegion: "Notifications",
  NotFoundTitle: "Page not found",
  NotFoundDescription: "The page you are looking for does not exist or has been moved.",
  BackToHome: "Back to home",
  GenericErrorTitle: "Something went wrong",
  GenericErrorDescription: "An unexpected error occurred. Please try again.",
  PreviousPage: "Previous",
  NextPage: "Next",
  Pagination: "Pagination",
} as const;

export function formatPageIndicator(currentPage: number, lastPage: number): string {
  return `Page ${currentPage} of ${lastPage}`;
}

export function formatRetryAfterMessage(seconds: number): string {
  const unit = seconds === 1 ? "second" : "seconds";

  return `Too many attempts. Please try again in ${seconds} ${unit}.`;
}
