// Dialog with the create user form that sends the name, email and role to the backend.
// Hands the issued credentials to the parent, which then shows the one time temporary password dialog.

"use client";

import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { Dialog } from "@/components/shared/dialog";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { Select } from "@/components/shared/select";
import { UiMessage } from "@/constants/ui-messages";
import {
  CREATE_USER_FIELDS,
  CreateUserField,
  type CreateUserFieldName,
} from "@/constants/user-fields";
import { USER_ROLE_OPTIONS, UserMessage } from "@/constants/user-messages";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import type { UserRole } from "@/enums/user-role";
import {
  emptyFormErrors,
  resolveFormErrors,
  type FormErrors,
} from "@/lib/forms/resolve-form-errors";
import { ApiError } from "@/lib/http/api-error";
import { userService } from "@/services/users/user-service";
import type { CreateUserRequest } from "@/types/users/create-user-request";
import type { IssuedCredentials } from "@/types/users/issued-credentials";

interface CreateUserFormValues {
  name: string;
  email: string;
  role: UserRole | "";
}

const EMPTY_FORM_VALUES: CreateUserFormValues = { name: "", email: "", role: "" };

interface CreateUserDialogProps {
  open: boolean;
  onClose: () => void;
  onCreated: (credentials: IssuedCredentials) => void;
}

export function CreateUserDialog({ open, onClose, onCreated }: CreateUserDialogProps) {
  const [values, setValues] = useState<CreateUserFormValues>(EMPTY_FORM_VALUES);
  const [errors, setErrors] = useState<FormErrors<CreateUserFieldName>>(emptyFormErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event: ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
    const { name, value } = event.target;
    setValues((current) => ({ ...current, [name]: value }));
  }

  function resetForm() {
    setValues(EMPTY_FORM_VALUES);
    setErrors(emptyFormErrors());
  }

  function handleClose() {
    if (isSubmitting) {
      return;
    }

    resetForm();
    onClose();
  }

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    if (values.role === "") {
      return;
    }

    const request: CreateUserRequest = { name: values.name, email: values.email, role: values.role };
    setIsSubmitting(true);
    setErrors(emptyFormErrors());

    try {
      const credentials = await userService.createUser(request);
      resetForm();
      onCreated(credentials);
    } catch (error) {
      setErrors(resolveFormErrors(error instanceof ApiError ? error : null, CREATE_USER_FIELDS));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title={UserMessage.CreateDialogTitle}
      description={UserMessage.CreateDialogDescription}
      dismissible={!isSubmitting}
    >
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
        <FormField
          label={UserMessage.NameLabel}
          error={errors.fieldErrors[CreateUserField.Name]}
          required
        >
          {(controlProps) => (
            <Input
              {...controlProps}
              name={CreateUserField.Name}
              value={values.name}
              onChange={handleChange}
              disabled={isSubmitting}
            />
          )}
        </FormField>
        <FormField
          label={UserMessage.EmailLabel}
          error={errors.fieldErrors[CreateUserField.Email]}
          required
        >
          {(controlProps) => (
            <Input
              {...controlProps}
              type="email"
              name={CreateUserField.Email}
              value={values.email}
              onChange={handleChange}
              disabled={isSubmitting}
            />
          )}
        </FormField>
        <FormField
          label={UserMessage.RoleLabel}
          error={errors.fieldErrors[CreateUserField.Role]}
          required
        >
          {(controlProps) => (
            <Select
              {...controlProps}
              name={CreateUserField.Role}
              options={USER_ROLE_OPTIONS}
              placeholder={UserMessage.RolePlaceholder}
              value={values.role}
              onChange={handleChange}
              disabled={isSubmitting}
            />
          )}
        </FormField>
        <div className="flex justify-end gap-3">
          <Button variant={ButtonVariant.Ghost} onClick={handleClose} disabled={isSubmitting}>
            {UiMessage.Cancel}
          </Button>
          <Button type="submit" isLoading={isSubmitting}>
            {UserMessage.CreateSubmit}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
