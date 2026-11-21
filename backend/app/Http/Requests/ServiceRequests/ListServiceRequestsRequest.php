<?php

// Validates the page, status, assignee, priority and title search query of both request lists and trims the search term.
// Builds the filters for the service request service and the filter query kept in the pagination links.

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\ServiceRequestFilters;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class ListServiceRequestsRequest extends FormRequest
{
    public const string PAGE_FIELD = 'page';

    public const string STATUS_FIELD = 'status';

    public const string ASSIGNEE_ID_FIELD = 'assignee_id';

    public const string PRIORITY_FIELD = 'priority';

    public const string SEARCH_FIELD = 'search';

    private const array FILTER_FIELDS = [
        self::STATUS_FIELD,
        self::ASSIGNEE_ID_FIELD,
        self::PRIORITY_FIELD,
        self::SEARCH_FIELD,
    ];

    private const int FIRST_PAGE = 1;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::PAGE_FIELD => ['nullable', 'integer', 'min:'.self::FIRST_PAGE],
            self::STATUS_FIELD => ['nullable', 'string', Rule::enum(ServiceRequestStatus::class)],
            self::ASSIGNEE_ID_FIELD => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->whereIn('role', UserRole::staffValues()),
            ],
            self::PRIORITY_FIELD => ['nullable', 'string', Rule::enum(ServiceRequestPriority::class)],
            self::SEARCH_FIELD => ['nullable', 'string', 'max:'.ServiceRequestLimits::TITLE_MAX_LENGTH],
        ];
    }

    public function toFilters(): ServiceRequestFilters
    {
        $validated = $this->validated();
        $status = $validated[self::STATUS_FIELD] ?? null;
        $assigneeId = $validated[self::ASSIGNEE_ID_FIELD] ?? null;
        $priority = $validated[self::PRIORITY_FIELD] ?? null;

        return new ServiceRequestFilters(
            $status === null ? null : ServiceRequestStatus::from($status),
            $assigneeId === null ? null : (int) $assigneeId,
            $priority === null ? null : ServiceRequestPriority::from($priority),
            $validated[self::SEARCH_FIELD] ?? null,
        );
    }

    public function filterQuery(): array
    {
        return array_filter(
            Arr::only($this->validated(), self::FILTER_FIELDS),
            fn (mixed $value): bool => $value !== null,
        );
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input(self::SEARCH_FIELD);

        if (is_string($search)) {
            $trimmedSearch = trim($search);

            $this->merge([self::SEARCH_FIELD => $trimmedSearch === '' ? null : $trimmedSearch]);
        }
    }
}
