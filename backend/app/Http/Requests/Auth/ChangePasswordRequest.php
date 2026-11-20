<?php

// Validates a self service password change against the central password policy.
// Builds the password change object consumed by the password change service.

namespace App\Http\Requests\Auth;

use App\Data\Auth\PasswordChange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    public const string CURRENT_PASSWORD_FIELD = 'current_password';

    public const string PASSWORD_FIELD = 'password';

    public const int MAX_PASSWORD_LENGTH = 72;

    private const string WEB_GUARD = 'web';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::CURRENT_PASSWORD_FIELD => ['required', 'string', 'current_password:'.self::WEB_GUARD],
            self::PASSWORD_FIELD => [
                'required',
                'string',
                'max:'.self::MAX_PASSWORD_LENGTH,
                'confirmed',
                Password::defaults(),
                'different:'.self::CURRENT_PASSWORD_FIELD,
            ],
        ];
    }

    public function toPasswordChange(): PasswordChange
    {
        return new PasswordChange($this->validated()[self::PASSWORD_FIELD]);
    }
}
