// Text of the sessions section that differs between the account page and the user detail page.
// Text shared by every sessions table stays in SessionMessage.

export interface SessionSectionCopy {
  title: string;
  description: string;
  endAllLabel: string;
  endAllTitle: string;
  endAllDescription: string;
  endAllSucceeded: string;
  nothingToEnd: string;
  loadFailed: string;
}
