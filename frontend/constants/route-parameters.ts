// Patterns that dynamic route parameters must match before a page uses them.
// Mirrors the backend route constraints and stays within the safe integer range, so invalid ids never reach the API.

export const POSITIVE_WHOLE_NUMBER_PATTERN = /^[1-9][0-9]{0,14}$/;
