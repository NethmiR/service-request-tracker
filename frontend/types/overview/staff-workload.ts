// Current workload of one technician or admin, mirrored from the backend StaffWorkloadResource.
// Assigned and in progress requests are counted separately so the chart can stack them.

import type { UserReference } from "@/types/shared/user-reference";

export interface StaffWorkload {
  user: UserReference;
  assigned: number;
  in_progress: number;
}
