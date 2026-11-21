<?php

// Validates the new assignee and remark of a service request being reassigned.
// Builds the reassignment object consumed by the workflow service.

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\Reassignment;
use App\Enums\UserRole;
use App\Http\Requests\ServiceRequests\Concerns\NormalizesRemark;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReassignServiceRequestRequest extends FormRequest
{
    use NormalizesRemark;

    public const string ASSIGNEE_ID_FIELD = 'assignee_id';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::ASSIGNEE_ID_FIELD => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->whereIn('role', UserRole::staffValues()),
            ],
            self::REMARK_FIELD => ['required', 'string', 'max:'.ServiceRequestLimits::REMARK_MAX_LENGTH],
        ];
    }

    public function toReassignment(): Reassignment
    {
        $validated = $this->validated();

        return new Reassignment(
            (int) $validated[self::ASSIGNEE_ID_FIELD],
            $validated[self::REMARK_FIELD],
        );
    }
}
