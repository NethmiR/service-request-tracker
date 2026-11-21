<?php

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\ServiceRequestFilters;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class ListServiceRequestsRequest extends FormRequest
{
    public const string PAGE_FIELD = 'page';

    public const string STATUS_FIELD = 'status';

    public const string ASSIGNEE_ID_FIELD = 'assignee_id';

    private const array FILTER_FIELDS = [
        self::STATUS_FIELD,
        self::ASSIGNEE_ID_FIELD,
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
        ];
    }

    public function toFilters(): ServiceRequestFilters
    {
        $validated = $this->validated();
        $status = $validated[self::STATUS_FIELD] ?? null;
        $assigneeId = $validated[self::ASSIGNEE_ID_FIELD] ?? null;

        return new ServiceRequestFilters(
            $status === null ? null : ServiceRequestStatus::from($status),
            $assigneeId === null ? null : (int) $assigneeId,
        );
    }

    public function filterQuery(): array
    {
        return array_filter(
            Arr::only($this->validated(), self::FILTER_FIELDS),
            fn (mixed $value): bool => $value !== null,
        );
    }
}
