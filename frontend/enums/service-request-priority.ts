// Predefined priorities a service request can carry, from lowest to highest, mirrored from the backend enum.
// Member names and values must stay identical on both sides.

export enum ServiceRequestPriority {
  Low = "LOW",
  Medium = "MEDIUM",
  High = "HIGH",
  Critical = "CRITICAL",
}
