// Sample text and data for the temporary base component preview.
// Phase 2 deletes this file together with the preview page.

import { ServiceRequestStatus } from "@/enums/service-request-status";
import { BadgeTone } from "@/enums/ui/badge-tone";

export const ComponentPreviewText = {
  PageTitle: "Base Component Preview",
  PageDescription: "Every shared component in its main states. Phase 2 replaces this page.",
  PhaseLabel: "Phase 1",
  ButtonsSection: "Buttons",
  ButtonsDescription: "Variants, sizes, loading and disabled states.",
  FormSection: "Form controls",
  FormDescription: "Inputs, selects, text areas and checkboxes wrapped in form fields.",
  BadgesSection: "Badges",
  BadgesDescription: "Status and role labels.",
  StatCardsSection: "Stat cards",
  StatCardsDescription: "Count boxes used on the overview page.",
  TableSection: "Tables",
  TableDescription: "A table with rows, pagination and an empty table.",
  AlertsSection: "Alerts",
  AlertsDescription: "Inline page level messages in every tone.",
  FeedbackSection: "Loading and empty states",
  FeedbackDescription: "Spinners and the empty state placeholder.",
  OverlaysSection: "Dialog and notifications",
  OverlaysDescription: "A modal dialog and toast notifications of every type.",
  PrimaryButton: "Primary",
  SecondaryButton: "Secondary",
  DangerButton: "Danger",
  GhostButton: "Ghost",
  SmallButton: "Small",
  MediumButton: "Medium",
  LargeButton: "Large",
  LoadingButton: "Saving",
  DisabledButton: "Disabled",
  TitleLabel: "Title",
  TitlePlaceholder: "Laptop does not start",
  TitleHint: "Keep it short and specific.",
  EmailLabel: "Email",
  EmailValue: "not-an-email",
  EmailError: "The email field must be a valid email address.",
  RoleLabel: "Role",
  RolePlaceholder: "Select a role",
  DescriptionLabel: "Description",
  DescriptionPlaceholder: "Describe the problem and when it started.",
  TagsLabel: "Tags",
  DisabledLabel: "Requested by",
  DisabledValue: "Signed in user",
  IdColumn: "ID",
  TitleColumn: "Title",
  StatusColumn: "Status",
  RequestedByColumn: "Requested by",
  TableCaption: "Sample service requests",
  EmptyTableTitle: "No service requests yet",
  EmptyTableDescription: "Requests you raise will appear here.",
  InfoAlertTitle: "Information",
  InfoAlertBody: "Your request was received and is waiting for a technician.",
  SuccessAlertTitle: "Saved",
  SuccessAlertBody: "The request was resolved successfully.",
  WarningAlertTitle: "Heads up",
  WarningAlertBody: "This request has had no status change for several days.",
  DangerAlertTitle: "Could not load requests",
  DangerAlertBody: "Something went wrong. Please try again later.",
  EmptyStateAction: "Create request",
  OpenDialog: "Open dialog",
  DialogTitle: "Assign to me",
  DialogDescription: "A remark is required for every assignment.",
  DialogBody: "You are about to take ownership of this request.",
  SuccessToast: "Show success toast",
  ErrorToast: "Show error toast",
  InfoToast: "Show info toast",
  WarningToast: "Show warning toast",
  SuccessToastMessage: "The request was assigned to you.",
  ErrorToastMessage: "Someone else changed this request first.",
  InfoToastMessage: "Your session list was refreshed.",
  WarningToastMessage: "Too many attempts. Please wait and try again.",
} as const;

export const PREVIEW_STATUS_TONES: Record<ServiceRequestStatus, BadgeTone> = {
  [ServiceRequestStatus.Open]: BadgeTone.Info,
  [ServiceRequestStatus.Assigned]: BadgeTone.Warning,
  [ServiceRequestStatus.InProgress]: BadgeTone.Neutral,
  [ServiceRequestStatus.Resolved]: BadgeTone.Success,
};

export interface PreviewServiceRequest {
  id: number;
  title: string;
  status: ServiceRequestStatus;
  requested_by: string;
}

export const PREVIEW_SERVICE_REQUESTS: PreviewServiceRequest[] = [
  { id: 1042, title: "Laptop does not start", status: ServiceRequestStatus.Open, requested_by: "Amaya Perera" },
  { id: 1041, title: "VPN disconnects every hour", status: ServiceRequestStatus.Assigned, requested_by: "Kasun Silva" },
  { id: 1040, title: "Install design software", status: ServiceRequestStatus.InProgress, requested_by: "Dilini Fernando" },
  { id: 1039, title: "Printer on floor two is offline", status: ServiceRequestStatus.Resolved, requested_by: "Ruwan Jayasuriya" },
];

export const PREVIEW_STAT_COUNTS: Record<ServiceRequestStatus, number> = {
  [ServiceRequestStatus.Open]: 12,
  [ServiceRequestStatus.Assigned]: 7,
  [ServiceRequestStatus.InProgress]: 4,
  [ServiceRequestStatus.Resolved]: 1280,
};

export const PREVIEW_LAST_PAGE = 5;
