// Dialog that asks the user to confirm an action before it runs, built on the shared dialog.
// While the action runs, both buttons are disabled and Escape is ignored so the request keeps its feedback.

"use client";

import { Button } from "@/components/shared/button";
import { Dialog } from "@/components/shared/dialog";
import { UiMessage } from "@/constants/ui-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  confirmVariant?: ButtonVariant;
  isConfirming: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  confirmVariant = ButtonVariant.Danger,
  isConfirming,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  function handleClose() {
    if (!isConfirming) {
      onCancel();
    }
  }

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title={title}
      description={description}
      dismissible={!isConfirming}
      footer={
        <>
          <Button variant={ButtonVariant.Ghost} onClick={onCancel} disabled={isConfirming}>
            {UiMessage.Cancel}
          </Button>
          <Button variant={confirmVariant} onClick={onConfirm} isLoading={isConfirming}>
            {confirmLabel}
          </Button>
        </>
      }
    />
  );
}
