// Sign in form with email and password that signs the user in through the auth context.
// Shows the generic failure, retry delay and field errors, while RequireGuest handles navigation.

"use client";

import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { Alert } from "@/components/shared/alert";
import { Button } from "@/components/shared/button";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { SIGN_IN_FIELDS, SignInField, type SignInFieldName } from "@/constants/auth-fields";
import { AuthMessage } from "@/constants/auth-messages";
import { ErrorCode } from "@/enums/error-code";
import { AlertTone } from "@/enums/ui/alert-tone";
import { AutocompleteToken } from "@/enums/ui/autocomplete-token";
import { ComponentSize } from "@/enums/ui/component-size";
import { useAuth } from "@/hooks/auth/use-auth";
import {
  emptyAuthFormErrors,
  resolveAuthFormErrors,
  type AuthFormErrors,
} from "@/lib/auth/resolve-auth-form-errors";
import { ApiError } from "@/lib/http/api-error";
import type { SignInRequest } from "@/types/auth/sign-in-request";

const EMPTY_SIGN_IN_REQUEST: SignInRequest = { email: "", password: "" };

export function SignInForm() {
  const { signIn } = useAuth();
  const [values, setValues] = useState<SignInRequest>(EMPTY_SIGN_IN_REQUEST);
  const [errors, setErrors] = useState<AuthFormErrors<SignInFieldName>>(emptyAuthFormErrors);
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
      await signIn(values);
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;
      setErrors(resolveAuthFormErrors(apiError, SIGN_IN_FIELDS));

      if (apiError?.hasCode(ErrorCode.InvalidCredentials)) {
        setValues((current) => ({ ...current, password: "" }));
      }

      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
      <FormField label={AuthMessage.EmailLabel} error={errors.fieldErrors[SignInField.Email]} required>
        {(controlProps) => (
          <Input
            {...controlProps}
            type="email"
            name={SignInField.Email}
            autoComplete={AutocompleteToken.Username}
            value={values.email}
            onChange={handleChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <FormField label={AuthMessage.PasswordLabel} error={errors.fieldErrors[SignInField.Password]} required>
        {(controlProps) => (
          <Input
            {...controlProps}
            type="password"
            name={SignInField.Password}
            autoComplete={AutocompleteToken.CurrentPassword}
            value={values.password}
            onChange={handleChange}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <Button type="submit" size={ComponentSize.Large} isLoading={isSubmitting} className="w-full">
        {AuthMessage.SignInButton}
      </Button>
    </form>
  );
}
