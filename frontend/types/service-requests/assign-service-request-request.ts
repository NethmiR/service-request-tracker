// Body of the call that assigns an open service request to the signed in user.
// The assignee is always the signed in user taken from the session, so it is never sent.

export interface AssignServiceRequestRequest {
  remark: string;
}
