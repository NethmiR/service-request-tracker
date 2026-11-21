<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public const array USERS = [
        ['name' => 'Technician 1', 'email' => 'tech1@test.com', 'password' => 'Tech1@test.com', 'role' => UserRole::Technician],
        ['name' => 'Administrator 1', 'email' => 'admin1@test.com', 'password' => 'Admin1@test.com', 'role' => UserRole::Admin],
        ['name' => 'Administrator 2', 'email' => 'admin2@test.com', 'password' => 'Admin2@test.com', 'role' => UserRole::Admin],
        ['name' => 'Requester 1', 'email' => 'req1@test.com', 'password' => 'Req1@test.com', 'role' => UserRole::Requester],
        ['name' => 'Technician 2', 'email' => 'tech2@test.com', 'password' => 'Tech2@test.com', 'role' => UserRole::Technician],
    ];

    private const string NAME = 'name';

    private const string EMAIL = 'email';

    private const string PASSWORD = 'password';

    private const string ROLE = 'role';

    public static function emailsWithRoles(UserRole ...$roles): array
    {
        $users = array_filter(self::USERS, fn (array $user): bool => in_array($user[self::ROLE], $roles, true));

        return array_column($users, self::EMAIL);
    }

    public function run(): void
    {
        foreach (self::USERS as $details) {
            $this->createUnlessExists($details);
        }
    }

    private function createUnlessExists(array $details): void
    {
        if (User::query()->where(self::EMAIL, $details[self::EMAIL])->exists()) {
            return;
        }

        $user = new User([
            self::NAME => $details[self::NAME],
            self::EMAIL => $details[self::EMAIL],
            self::PASSWORD => $details[self::PASSWORD],
            self::ROLE => $details[self::ROLE],
        ]);
        $user->markPasswordChanged();
        $user->save();
    }
}
