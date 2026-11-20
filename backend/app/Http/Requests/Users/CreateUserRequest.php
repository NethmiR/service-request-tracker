<?php

// Validates the create user payload and normalizes the name and email.
// Builds the new user object consumed by the user management service.

namespace App\Http\Requests\Users;

use App\Data\Users\NewUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CreateUserRequest extends FormRequest
{
    public const string NAME_FIELD = 'name';

    public const string EMAIL_FIELD = 'email';

    public const string ROLE_FIELD = 'role';

    private const int MAX_LENGTH = 255;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            self::NAME_FIELD => ['required', 'string', 'max:'.self::MAX_LENGTH],
            self::EMAIL_FIELD => [
                'required',
                'string',
                'email',
                'max:'.self::MAX_LENGTH,
                Rule::unique(User::class, self::EMAIL_FIELD),
            ],
            self::ROLE_FIELD => ['required', 'string', Rule::enum(UserRole::class)],
        ];
    }

    public function toNewUser(): NewUser
    {
        $validated = $this->validated();

        return new NewUser(
            $validated[self::NAME_FIELD],
            $validated[self::EMAIL_FIELD],
            UserRole::from($validated[self::ROLE_FIELD]),
        );
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input(self::NAME_FIELD);
        $email = $this->input(self::EMAIL_FIELD);

        if (is_string($name)) {
            $this->merge([self::NAME_FIELD => trim($name)]);
        }

        if (is_string($email)) {
            $this->merge([self::EMAIL_FIELD => Str::lower(trim($email))]);
        }
    }
}
