// Password policy values shown to users, mirroring the backend policy.
// The backend still validates every password against the full policy.

export const MINIMUM_PASSWORD_LENGTH = 12;

export const PASSWORD_POLICY_HINT = `Use at least ${MINIMUM_PASSWORD_LENGTH} characters with upper and lower case letters, numbers and symbols.`;
