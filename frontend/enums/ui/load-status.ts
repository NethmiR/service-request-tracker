// States of a list loaded from the API, such as the active sessions.
// Used only by the frontend.

export enum LoadStatus {
  Loading = "loading",
  Loaded = "loaded",
  Failed = "failed",
}
