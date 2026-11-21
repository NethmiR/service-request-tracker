<?php

// Validates the remark of a user assigning an open service request to themselves.
// Builds the self assignment object consumed by the workflow service.

namespace App\Http\Requests\ServiceRequests;

use App\Data\ServiceRequests\SelfAssignment;
use App\Http\Requests\ServiceRequests\Concerns\NormalizesRemark;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Http\FormRequest;

final class AssignServiceRequestRequest extends FormRequest
{
    use NormalizesRemark;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::REMARK_FIELD => ['required', 'string', 'max:'.ServiceRequestLimits::REMARK_MAX_LENGTH],
        ];
    }

    public function toSelfAssignment(): SelfAssignment
    {
        return new SelfAssignment($this->validated()[self::REMARK_FIELD]);
    }
}
