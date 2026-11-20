// One assignee history record of a service request, mirrored from the backend resource.
// The previous assignee is null for the first assignment, and the remark is always present.

import type { UserReference } from "@/types/shared/user-reference";

export interface AssigneeHistoryEntry {
  id: number;
  assigned_from: UserReference | null;
  assigned_to: UserReference;
  remark: string;
  created_at: string;
}
