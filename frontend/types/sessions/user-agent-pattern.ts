// One entry of the ordered tables that turn a user agent into a browser or platform label.
// The first pattern that matches the user agent decides the label.

export interface UserAgentPattern {
  pattern: RegExp;
  label: string;
}
