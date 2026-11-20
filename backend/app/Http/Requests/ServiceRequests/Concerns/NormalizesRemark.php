<?php

// Trims the remark of a workflow request and turns a value that is empty after trimming into null.
// Every workflow Form Request uses it, so the remark is normalized in one place.

namespace App\Http\Requests\ServiceRequests\Concerns;

trait NormalizesRemark
{
    public const string REMARK_FIELD = 'remark';

    protected function prepareForValidation(): void
    {
        $remark = $this->input(self::REMARK_FIELD);

        if (is_string($remark)) {
            $trimmed = trim($remark);
            $this->merge([self::REMARK_FIELD => $trimmed === '' ? null : $trimmed]);
        }
    }
}
