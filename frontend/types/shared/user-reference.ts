// Minimal reference to a user shown on service request screens, such as the requester or an assignee.
// Mirrors the backend UserReferenceResource, which never exposes emails or roles.

export interface UserReference {
  id: number;
  name: string;
}
