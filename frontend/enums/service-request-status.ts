// Statuses a service request moves through, mirrored from the backend enum.
// Member names and values must stay identical on both sides.

export enum ServiceRequestStatus {
  Open = "OPEN",
  Assigned = "ASSIGNED",
  InProgress = "IN_PROGRESS",
  Resolved = "RESOLVED",
}
