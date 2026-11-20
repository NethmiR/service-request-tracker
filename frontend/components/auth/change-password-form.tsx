// Reusable form for changing the signed in user's password through the auth context.
// Password values live only in component state and are cleared once the change succeeds.

"use client";

import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import {
  CHANGE_PASSWORD_FIELDS,
  ChangePasswordField,
  type ChangePasswordFieldName,
} from "@/constants/auth-fields";
import { AuthMessage } from "@/constants/auth-messages";
import { PASSWORD_POLICY_HINT } from "@/constants/password-policy";
import { AlertTone } from "@/enums/ui/alert-tone";
import { AutocompleteToken } from "@/enums/ui/autocomplete-token";
import { NotificationType } from "@/enums/ui/notification-type";
import { useAuth } from "@/hooks/auth/use-auth";
import { useNotification } from "@/hooks/shared/use-notification";
import {
  emptyAuthFormErrors,
  resolveAuthFormErrors,
  type AuthFormErrors,
} from "@/lib/auth/resolve-auth-form-errors";
import { ApiError } from "@/lib/http/api-error";
import type { ChangePasswordRequest } from "@/types/auth/change-password-request";
import type { CurrentUser } from "@/types/auth/current-user";

const EMPTY_CHANGE_PASSWORD_REQUEST: ChangePasswordRequest = {
  current_password: "",
  password: "",
  password_confirmation: "",
};

interface ChangePasswordFormProps {
  onSuccess: (user: CurrentUser) => void;
}

export function ChangePasswordForm({ onSuccess }: ChangePasswordFormProps) {
  const { changePassword } = useAuth();
  const { notify } = useNotification();
  const [values, setValues] = useState<ChangePasswordRequest>(EMPTY_CHANGE_PASSWORD_REQUEST);
  const [errors, setErrors] =
    useState<AuthFormErrors<ChangePasswordFieldName>>(emptyAuthFormErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const { name, value } = event.target;
    setValues((current) => ({ ...current, [name]: value }));
  }

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors(emptyAuthFormErrors());

    try {
      const user = await changePassword(values);
      setValues(EMPTY_CHANGE_PASSWORD_REQUEST);
      notify(NotificationType.Success, AuthMessage.PasswordChanged);
      onSuccess(user);
    } catch (error) {
      setErrors(
        resolveAuthFormErrors(error instanceof ApiError ? error : null, CHANGE_PASSWORD_FIELDS),
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
      <FormField
        label={AuthMessage.CurrentPasswordLabel}
        error={errors.fieldErrors[ChangePasswordField.CurrentPassword]}
        required
      >
        {(controlProps) => (
          <Input
            {...controlProps}
            type="password"
            name={ChangePasswordField.CurrentPassword}
            autoComplete={AutocompleteToken.CurrentPassword}
            value={values.current_password}
            onChange={handleChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <FormField
        label={AuthMessage.NewPasswordLabel}
        hint={PASSWORD_POLICY_HINT}
        error={errors.fieldErrors[ChangePasswordField.Password]}
        required
      >
        {(controlProps) => (
          <Input
            {...controlProps}
            type="password"
            name={ChangePasswordField.Password}
            autoComplete={AutocompleteToken.NewPassword}
            value={values.password}
            onChange={handleChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <FormField
        label={AuthMessage.ConfirmPasswordLabel}
        error={errors.fieldErrors[ChangePasswordField.PasswordConfirmation]}
        required
      >
        {(controlProps) => (
          <Input
            {...controlProps}
            type="password"
            name={ChangePasswordField.PasswordConfirmation}
            autoComplete={AutocompleteToken.NewPassword}
            value={values.password_confirmation}
            onChange={handleChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <div className="flex justify-end">
        <Button type="submit" isLoading={isSubmitting}>
          {AuthMessage.ChangePasswordButton}
        </Button>
      </div>
    </form>
  );
}
