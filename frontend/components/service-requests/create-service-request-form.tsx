// Form that raises a new service request with a title, a description and at least one tag.
// The tag and length checks are only a convenience, the backend validates again and sets the requester and status.
// On success it shows a notification and opens the detail page of the new request.

"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import type { ChangeEvent, SubmitEvent } from "react";
import { Alert } from "@/components/shared/alert";
import { Button, getButtonClassNames } from "@/components/shared/button";
import { CheckboxGroup } from "@/components/shared/checkbox-group";
import { FormField } from "@/components/shared/form-field";
import { Input } from "@/components/shared/input";
import { Textarea } from "@/components/shared/textarea";
import { AppRoute, serviceRequestDetailRoute } from "@/constants/routes";
import {
  CREATE_SERVICE_REQUEST_FIELDS,
  CreateServiceRequestField,
  type CreateServiceRequestFieldName,
} from "@/constants/service-request-fields";
import {
  SERVICE_REQUEST_DESCRIPTION_MAX_LENGTH,
  SERVICE_REQUEST_TITLE_MAX_LENGTH,
} from "@/constants/service-request-limits";
import {
  SERVICE_REQUEST_TAG_OPTIONS,
  ServiceRequestMessage,
  formatCharacterLimit,
} from "@/constants/service-request-messages";
import type { ServiceRequestTag } from "@/enums/service-request-tag";
import { AlertTone } from "@/enums/ui/alert-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { NotificationType } from "@/enums/ui/notification-type";
import { useNotification } from "@/hooks/shared/use-notification";
import {
  emptyFormErrors,
  resolveFormErrors,
  type FormErrors,
} from "@/lib/forms/resolve-form-errors";
import { ApiError } from "@/lib/http/api-error";
import { isHandledByRouteGuards } from "@/lib/http/is-handled-by-route-guards";
import { serviceRequestService } from "@/services/service-requests/service-request-service";
import type { CreateServiceRequestRequest } from "@/types/service-requests/create-service-request-request";

const DESCRIPTION_ROWS = 8;

export function CreateServiceRequestForm() {
  const router = useRouter();
  const { notify } = useNotification();
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [tags, setTags] = useState<ServiceRequestTag[]>([]);
  const [errors, setErrors] =
    useState<FormErrors<CreateServiceRequestFieldName>>(emptyFormErrors);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleTagsChange(nextTags: ServiceRequestTag[]) {
    setTags(nextTags);

    if (nextTags.length === 0) {
      return;
    }

    setErrors((current) => {
      const fieldErrors = { ...current.fieldErrors };
      delete fieldErrors[CreateServiceRequestField.Tags];

      return { ...current, fieldErrors };
    });
  }

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    if (tags.length === 0) {
      setErrors({
        message: null,
        fieldErrors: { [CreateServiceRequestField.Tags]: ServiceRequestMessage.TagsRequired },
      });
      return;
    }

    const request: CreateServiceRequestRequest = { title, description, tags };
    setIsSubmitting(true);
    setErrors(emptyFormErrors());

    try {
      const serviceRequest = await serviceRequestService.createServiceRequest(request);
      notify(NotificationType.Success, ServiceRequestMessage.RequestCreated);
      router.push(serviceRequestDetailRoute(serviceRequest.id));
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null;

      if (!isHandledByRouteGuards(apiError)) {
        setErrors(resolveFormErrors(apiError, CREATE_SERVICE_REQUEST_FIELDS));
      }

      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {errors.message !== null && <Alert tone={AlertTone.Danger}>{errors.message}</Alert>}
      <FormField
        label={ServiceRequestMessage.TitleLabel}
        hint={formatCharacterLimit(SERVICE_REQUEST_TITLE_MAX_LENGTH)}
        error={errors.fieldErrors[CreateServiceRequestField.Title]}
        required
      >
        {(controlProps) => (
          <Input
            {...controlProps}
            name={CreateServiceRequestField.Title}
            value={title}
            maxLength={SERVICE_REQUEST_TITLE_MAX_LENGTH}
            placeholder={ServiceRequestMessage.TitlePlaceholder}
            onChange={(event: ChangeEvent<HTMLInputElement>) => setTitle(event.target.value)}
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <FormField
        label={ServiceRequestMessage.DescriptionLabel}
        hint={formatCharacterLimit(SERVICE_REQUEST_DESCRIPTION_MAX_LENGTH)}
        error={errors.fieldErrors[CreateServiceRequestField.Description]}
        required
      >
        {(controlProps) => (
          <Textarea
            {...controlProps}
            name={CreateServiceRequestField.Description}
            value={description}
            rows={DESCRIPTION_ROWS}
            maxLength={SERVICE_REQUEST_DESCRIPTION_MAX_LENGTH}
            placeholder={ServiceRequestMessage.DescriptionPlaceholder}
            onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
              setDescription(event.target.value)
            }
            disabled={isSubmitting}
          />
        )}
      </FormField>
      <CheckboxGroup
        legend={ServiceRequestMessage.TagsLegend}
        options={SERVICE_REQUEST_TAG_OPTIONS}
        values={tags}
        onChange={handleTagsChange}
        hint={ServiceRequestMessage.TagsHint}
        error={errors.fieldErrors[CreateServiceRequestField.Tags]}
        required
        disabled={isSubmitting}
      />
      <div className="flex flex-col-reverse gap-3 border-t border-border-subtle pt-5 sm:flex-row sm:justify-end">
        <Link
          href={AppRoute.MyServiceRequests}
          aria-disabled={isSubmitting || undefined}
          className={getButtonClassNames(
            ButtonVariant.Ghost,
            undefined,
            isSubmitting ? "pointer-events-none opacity-60" : undefined,
          )}
        >
          {ServiceRequestMessage.Cancel}
        </Link>
        <Button type="submit" isLoading={isSubmitting}>
          {ServiceRequestMessage.Submit}
        </Button>
      </div>
    </form>
  );
}
