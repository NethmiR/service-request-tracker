<?php

// Lists users, creates users with a temporary password and resets passwords on behalf of an admin.
// Every change and its audit record are written in one transaction, and the temporary password is delivered only after the commit.

namespace App\Services\Users;

use App\Contracts\Users\TemporaryPasswordDelivery;
use App\Data\Audit\AuthAuditEntry;
use App\Data\Auth\RequestContext;
use App\Data\Users\IssuedCredentials;
use App\Data\Users\NewUser;
use App\Enums\AuthAuditEvent;
use App\Http\Requests\Users\CreateUserRequest;
use App\Models\User;
use App\Services\Audit\AuthAuditLogger;
use App\Services\Sessions\SessionTerminator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserManagementService
{
    public const int PAGE_SIZE = 15;

    public function __construct(
        private readonly TemporaryPasswordGenerator $passwordGenerator,
        private readonly TemporaryPasswordDelivery $passwordDelivery,
        private readonly SessionTerminator $sessionTerminator,
        private readonly AuthAuditLogger $auditLogger,
    ) {}

    public function paginateUsers(): LengthAwarePaginator
    {
        return User::query()->orderByDesc('id')->paginate(self::PAGE_SIZE);
    }

    public function createUser(User $admin, NewUser $newUser, RequestContext $context): IssuedCredentials
    {
        $temporaryPassword = $this->passwordGenerator->generate();

        try {
            $user = DB::transaction(function () use ($admin, $newUser, $temporaryPassword, $context): User {
                $user = User::query()->create([
                    'name' => $newUser->name,
                    'email' => $newUser->email,
                    'role' => $newUser->role,
                    'password' => $temporaryPassword,
                ]);

                $this->auditLogger->record(new AuthAuditEntry(
                    AuthAuditEvent::UserCreatedByAdmin,
                    $admin,
                    $user,
                    null,
                    $context,
                ));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                CreateUserRequest::EMAIL_FIELD => [
                    __('validation.unique', ['attribute' => CreateUserRequest::EMAIL_FIELD]),
                ],
            ]);
        }

        return new IssuedCredentials($user, $this->passwordDelivery->deliver($user, $temporaryPassword));
    }

    public function resetPassword(User $admin, User $target, RequestContext $context): IssuedCredentials
    {
        $temporaryPassword = $this->passwordGenerator->generate();

        DB::transaction(function () use ($admin, $target, $temporaryPassword, $context): void {
            $target->password = $temporaryPassword;
            $target->markPasswordChangeRequired();
            $target->save();

            $this->sessionTerminator->terminateAllSessions($target);

            $this->auditLogger->record(new AuthAuditEntry(
                AuthAuditEvent::PasswordResetByAdmin,
                $admin,
                $target,
                null,
                $context,
            ));
        });

        $target->refresh();

        return new IssuedCredentials($target, $this->passwordDelivery->deliver($target, $temporaryPassword));
    }
}
