<?php

// Validates the create service request payload, including the required priority, and trims the title and description.
// Builds the new service request object consumed by the service request service.

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\NewServiceRequest;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestTag;
use App\Models\PriorityTag;
use App\Models\Tag;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateServiceRequestRequest extends FormRequest
{
    public const string TITLE_FIELD = 'title';

    public const string DESCRIPTION_FIELD = 'description';

    public const string TAGS_FIELD = 'tags';

    public const string PRIORITY_FIELD = 'priority';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::TITLE_FIELD => ['required', 'string', 'max:'.ServiceRequestLimits::TITLE_MAX_LENGTH],
            self::DESCRIPTION_FIELD => ['required', 'string', 'max:'.ServiceRequestLimits::DESCRIPTION_MAX_LENGTH],
            self::TAGS_FIELD => ['required', 'array', 'list', 'min:1'],
            self::TAGS_FIELD.'.*' => [
                'required',
                'string',
                'distinct',
                Rule::enum(ServiceRequestTag::class),
                Rule::exists(Tag::class, 'name'),
            ],
            self::PRIORITY_FIELD => [
                'required',
                'string',
                Rule::enum(ServiceRequestPriority::class),
                Rule::exists(PriorityTag::class, 'name'),
            ],
        ];
    }

    public function toNewServiceRequest(): NewServiceRequest
    {
        $validated = $this->validated();

        return new NewServiceRequest(
            $validated[self::TITLE_FIELD],
            $validated[self::DESCRIPTION_FIELD],
            array_map(ServiceRequestTag::from(...), $validated[self::TAGS_FIELD]),
            ServiceRequestPriority::from($validated[self::PRIORITY_FIELD]),
        );
    }

    protected function prepareForValidation(): void
    {
        foreach ([self::TITLE_FIELD, self::DESCRIPTION_FIELD] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }
}
