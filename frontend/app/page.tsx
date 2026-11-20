// Temporary preview of every base component in its main states.
// Phase 2 replaces it with the root redirect and deletes the preview component.

import { Alert } from "@/components/shared/alert";
import { Badge } from "@/components/shared/badge";
import { Button } from "@/components/shared/button";
import { ComponentPreview, PreviewSection } from "@/components/shared/component-preview";
import { EmptyState } from "@/components/shared/empty-state";
import { PageHeader } from "@/components/shared/page-header";
import { Spinner } from "@/components/shared/spinner";
import { StatCard } from "@/components/shared/stat-card";
import { ComponentPreviewText, PREVIEW_STAT_COUNTS, PREVIEW_STATUS_TONES } from "@/constants/component-preview";
import { SERVICE_REQUEST_STATUS_LABELS, USER_ROLE_LABELS } from "@/constants/enum-labels";
import { ServiceRequestStatus } from "@/enums/service-request-status";
import { UserRole } from "@/enums/user-role";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";

const alertSamples: { tone: AlertTone; title: string; body: string }[] = [
  { tone: AlertTone.Info, title: ComponentPreviewText.InfoAlertTitle, body: ComponentPreviewText.InfoAlertBody },
  { tone: AlertTone.Success, title: ComponentPreviewText.SuccessAlertTitle, body: ComponentPreviewText.SuccessAlertBody },
  { tone: AlertTone.Warning, title: ComponentPreviewText.WarningAlertTitle, body: ComponentPreviewText.WarningAlertBody },
  { tone: AlertTone.Danger, title: ComponentPreviewText.DangerAlertTitle, body: ComponentPreviewText.DangerAlertBody },
];

export default function Home() {
  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <PageHeader
        title={ComponentPreviewText.PageTitle}
        description={ComponentPreviewText.PageDescription}
        actions={<Badge>{ComponentPreviewText.PhaseLabel}</Badge>}
        className="rounded-lg bg-surface px-6 py-4"
      />
      <main className="flex flex-col gap-8 rounded-lg bg-surface p-6 sm:p-8">
        <PreviewSection
          title={ComponentPreviewText.ButtonsSection}
          description={ComponentPreviewText.ButtonsDescription}
        >
          <div className="flex flex-wrap items-center gap-3">
            <Button>{ComponentPreviewText.PrimaryButton}</Button>
            <Button variant={ButtonVariant.Secondary}>{ComponentPreviewText.SecondaryButton}</Button>
            <Button variant={ButtonVariant.Danger}>{ComponentPreviewText.DangerButton}</Button>
            <Button variant={ButtonVariant.Ghost}>{ComponentPreviewText.GhostButton}</Button>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <Button size={ComponentSize.Small}>{ComponentPreviewText.SmallButton}</Button>
            <Button size={ComponentSize.Medium}>{ComponentPreviewText.MediumButton}</Button>
            <Button size={ComponentSize.Large}>{ComponentPreviewText.LargeButton}</Button>
            <Button isLoading>{ComponentPreviewText.LoadingButton}</Button>
            <Button disabled>{ComponentPreviewText.DisabledButton}</Button>
          </div>
        </PreviewSection>

        <ComponentPreview />

        <PreviewSection
          title={ComponentPreviewText.BadgesSection}
          description={ComponentPreviewText.BadgesDescription}
        >
          <div className="flex flex-wrap gap-2">
            {Object.values(ServiceRequestStatus).map((status) => (
              <Badge key={status} tone={PREVIEW_STATUS_TONES[status]}>
                {SERVICE_REQUEST_STATUS_LABELS[status]}
              </Badge>
            ))}
            {Object.values(UserRole).map((role) => (
              <Badge key={role}>{USER_ROLE_LABELS[role]}</Badge>
            ))}
          </div>
        </PreviewSection>

        <PreviewSection
          title={ComponentPreviewText.StatCardsSection}
          description={ComponentPreviewText.StatCardsDescription}
        >
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Object.values(ServiceRequestStatus).map((status) => (
              <StatCard
                key={status}
                label={SERVICE_REQUEST_STATUS_LABELS[status]}
                value={PREVIEW_STAT_COUNTS[status]}
              />
            ))}
          </div>
        </PreviewSection>

        <PreviewSection
          title={ComponentPreviewText.AlertsSection}
          description={ComponentPreviewText.AlertsDescription}
        >
          <div className="flex flex-col gap-3">
            {alertSamples.map((sample) => (
              <Alert key={sample.tone} tone={sample.tone} title={sample.title}>
                {sample.body}
              </Alert>
            ))}
          </div>
        </PreviewSection>

        <PreviewSection
          title={ComponentPreviewText.FeedbackSection}
          description={ComponentPreviewText.FeedbackDescription}
        >
          <div className="flex items-center gap-6 text-primary">
            <Spinner size={ComponentSize.Small} />
            <Spinner size={ComponentSize.Medium} />
            <Spinner size={ComponentSize.Large} />
          </div>
          <EmptyState
            action={<Button>{ComponentPreviewText.EmptyStateAction}</Button>}
            className="rounded-lg border border-dashed border-border"
          />
        </PreviewSection>
      </main>
    </div>
  );
}
