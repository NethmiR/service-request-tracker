// Interactive sections of the temporary base component preview, such as the dialog and toasts.
// Phase 2 deletes this file together with the preview page.

"use client";

import { useState } from "react";
import type { ReactNode } from "react";
import { Badge } from "@/components/shared/badge";
import { Button } from "@/components/shared/button";
import { Checkbox } from "@/components/shared/checkbox";
import { Dialog } from "@/components/shared/dialog";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { Pagination } from "@/components/shared/pagination";
import { Select } from "@/components/shared/select";
import type { SelectOption } from "@/components/shared/select";
import { Table } from "@/components/shared/table";
import type { TableColumn } from "@/components/shared/table";
import { Textarea } from "@/components/shared/textarea";
import {
  ComponentPreviewText,
  PREVIEW_LAST_PAGE,
  PREVIEW_SERVICE_REQUESTS,
  PREVIEW_STATUS_TONES,
} from "@/constants/component-preview";
import type { PreviewServiceRequest } from "@/constants/component-preview";
import {
  SERVICE_REQUEST_STATUS_LABELS,
  SERVICE_REQUEST_TAG_LABELS,
  USER_ROLE_LABELS,
} from "@/constants/enum-labels";
import { UiMessage } from "@/constants/ui-messages";
import { ServiceRequestTag } from "@/enums/service-request-tag";
import { UserRole } from "@/enums/user-role";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import type { PaginationMeta } from "@/types/shared/paginated-response";

interface PreviewSectionProps {
  title: string;
  description: string;
  children: ReactNode;
}

export function PreviewSection({ title, description, children }: PreviewSectionProps) {
  return (
    <section className="flex flex-col gap-4 border-b border-border-subtle pb-8 last:border-b-0 last:pb-0">
      <div className="flex flex-col gap-1">
        <h2 className="text-xl font-semibold text-primary">{title}</h2>
        <p className="text-sm text-foreground">{description}</p>
      </div>
      {children}
    </section>
  );
}

const roleOptions: SelectOption<UserRole>[] = Object.values(UserRole).map((role) => ({
  value: role,
  label: USER_ROLE_LABELS[role],
}));

const tableColumns: TableColumn<PreviewServiceRequest>[] = [
  {
    key: "id",
    header: ComponentPreviewText.IdColumn,
    cell: (row) => row.id,
    className: "w-20 tabular-nums",
  },
  { key: "title", header: ComponentPreviewText.TitleColumn, cell: (row) => row.title },
  {
    key: "status",
    header: ComponentPreviewText.StatusColumn,
    cell: (row) => (
      <Badge tone={PREVIEW_STATUS_TONES[row.status]}>
        {SERVICE_REQUEST_STATUS_LABELS[row.status]}
      </Badge>
    ),
  },
  {
    key: "requested_by",
    header: ComponentPreviewText.RequestedByColumn,
    cell: (row) => row.requested_by,
  },
];

function buildPreviewMeta(currentPage: number): PaginationMeta {
  const perPage = PREVIEW_SERVICE_REQUESTS.length;

  return {
    current_page: currentPage,
    from: (currentPage - 1) * perPage + 1,
    last_page: PREVIEW_LAST_PAGE,
    path: "",
    per_page: perPage,
    to: currentPage * perPage,
    total: PREVIEW_LAST_PAGE * perPage,
    links: [],
  };
}

function FormPreview() {
  return (
    <div className="grid gap-x-8 gap-y-5 md:grid-cols-2">
      <FormField label={ComponentPreviewText.TitleLabel} hint={ComponentPreviewText.TitleHint} required>
        {(controlProps) => (
          <Input placeholder={ComponentPreviewText.TitlePlaceholder} {...controlProps} />
        )}
      </FormField>
      <FormField label={ComponentPreviewText.EmailLabel} error={ComponentPreviewText.EmailError} required>
        {(controlProps) => (
          <Input type="email" defaultValue={ComponentPreviewText.EmailValue} {...controlProps} />
        )}
      </FormField>
      <FormField label={ComponentPreviewText.RoleLabel} required>
        {(controlProps) => (
          <Select
            options={roleOptions}
            placeholder={ComponentPreviewText.RolePlaceholder}
            defaultValue=""
            {...controlProps}
          />
        )}
      </FormField>
      <FormField label={ComponentPreviewText.DisabledLabel}>
        {(controlProps) => (
          <Input defaultValue={ComponentPreviewText.DisabledValue} disabled {...controlProps} />
        )}
      </FormField>
      <FormField label={ComponentPreviewText.DescriptionLabel} className="md:col-span-2" required>
        {(controlProps) => (
          <Textarea placeholder={ComponentPreviewText.DescriptionPlaceholder} {...controlProps} />
        )}
      </FormField>
      <fieldset className="flex flex-col gap-2 md:col-span-2">
        <legend className="mb-1.5 text-sm font-medium text-foreground">
          {ComponentPreviewText.TagsLabel}
        </legend>
        <div className="flex flex-wrap gap-x-6 gap-y-2">
          {Object.values(ServiceRequestTag).map((tag) => (
            <Checkbox
              key={tag}
              name="tags"
              value={tag}
              label={SERVICE_REQUEST_TAG_LABELS[tag]}
              defaultChecked={tag === ServiceRequestTag.Hardware}
            />
          ))}
        </div>
      </fieldset>
    </div>
  );
}

function TablePreview() {
  const [currentPage, setCurrentPage] = useState(1);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3">
        <Table
          columns={tableColumns}
          rows={PREVIEW_SERVICE_REQUESTS}
          rowKey={(row) => row.id}
          caption={ComponentPreviewText.TableCaption}
        />
        <Pagination meta={buildPreviewMeta(currentPage)} onPageChange={setCurrentPage} />
      </div>
      <Table
        columns={tableColumns}
        rows={[]}
        rowKey={(row) => row.id}
        caption={ComponentPreviewText.TableCaption}
        emptyTitle={ComponentPreviewText.EmptyTableTitle}
        emptyDescription={ComponentPreviewText.EmptyTableDescription}
      />
    </div>
  );
}

const toastTriggers: { type: NotificationType; label: string; message: string }[] = [
  {
    type: NotificationType.Success,
    label: ComponentPreviewText.SuccessToast,
    message: ComponentPreviewText.SuccessToastMessage,
  },
  {
    type: NotificationType.Error,
    label: ComponentPreviewText.ErrorToast,
    message: ComponentPreviewText.ErrorToastMessage,
  },
  {
    type: NotificationType.Info,
    label: ComponentPreviewText.InfoToast,
    message: ComponentPreviewText.InfoToastMessage,
  },
  {
    type: NotificationType.Warning,
    label: ComponentPreviewText.WarningToast,
    message: ComponentPreviewText.WarningToastMessage,
  },
];

function OverlayPreview() {
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const { notify } = useNotification();

  function closeDialog() {
    setIsDialogOpen(false);
  }

  return (
    <div className="flex flex-wrap gap-3">
      <Button onClick={() => setIsDialogOpen(true)}>{ComponentPreviewText.OpenDialog}</Button>
      {toastTriggers.map((trigger) => (
        <Button
          key={trigger.type}
          variant={ButtonVariant.Secondary}
          onClick={() => notify(trigger.type, trigger.message)}
        >
          {trigger.label}
        </Button>
      ))}
      <Dialog
        open={isDialogOpen}
        onClose={closeDialog}
        title={ComponentPreviewText.DialogTitle}
        description={ComponentPreviewText.DialogDescription}
        footer={
          <>
            <Button variant={ButtonVariant.Ghost} onClick={closeDialog}>
              {UiMessage.Cancel}
            </Button>
            <Button onClick={closeDialog}>{UiMessage.Confirm}</Button>
          </>
        }
      >
        <p className="text-sm text-foreground">{ComponentPreviewText.DialogBody}</p>
      </Dialog>
    </div>
  );
}

export function ComponentPreview() {
  return (
    <>
      <PreviewSection
        title={ComponentPreviewText.FormSection}
        description={ComponentPreviewText.FormDescription}
      >
        <FormPreview />
      </PreviewSection>
      <PreviewSection
        title={ComponentPreviewText.TableSection}
        description={ComponentPreviewText.TableDescription}
      >
        <TablePreview />
      </PreviewSection>
      <PreviewSection
        title={ComponentPreviewText.OverlaysSection}
        description={ComponentPreviewText.OverlaysDescription}
      >
        <OverlayPreview />
      </PreviewSection>
    </>
  );
}
