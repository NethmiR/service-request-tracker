<?php

// Validates the target status and optional remark of a service request status change.
// Every status value passes here, so a forbidden target reaches the transition rules and returns a conflict.

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\StatusChange;
use App\Enums\ServiceRequestStatus;
use App\Http\Requests\ServiceRequests\Concerns\NormalizesRemark;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeServiceRequestStatusRequest extends FormRequest
{
    use NormalizesRemark;

    public const string STATUS_FIELD = 'status';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::STATUS_FIELD => ['required', 'string', Rule::enum(ServiceRequestStatus::class)],
            self::REMARK_FIELD => ['nullable', 'string', 'max:'.ServiceRequestLimits::REMARK_MAX_LENGTH],
        ];
    }

    public function toStatusChange(): StatusChange
    {
        $validated = $this->validated();

        return new StatusChange(
            ServiceRequestStatus::from($validated[self::STATUS_FIELD]),
            $validated[self::REMARK_FIELD] ?? null,
        );
    }
}
