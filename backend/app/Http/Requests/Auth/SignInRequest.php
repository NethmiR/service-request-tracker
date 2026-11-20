<?php

// Validates the sign in payload and normalizes the email.
// Builds the credentials object consumed by the authentication service.

namespace App\Http\Requests\Auth;

use App\Data\Auth\SignInCredentials;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class SignInRequest extends FormRequest
{
    public const string EMAIL_FIELD = 'email';

    public const string PASSWORD_FIELD = 'password';

    private const int MAX_LENGTH = 255;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::EMAIL_FIELD => ['required', 'string', 'email', 'max:'.self::MAX_LENGTH],
            self::PASSWORD_FIELD => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    public function toCredentials(): SignInCredentials
    {
        $validated = $this->validated();

        return new SignInCredentials(
            $validated[self::EMAIL_FIELD],
            $validated[self::PASSWORD_FIELD],
        );
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input(self::EMAIL_FIELD);

        if (is_string($email)) {
            $this->merge([self::EMAIL_FIELD => Str::lower(trim($email))]);
        }
    }
}
