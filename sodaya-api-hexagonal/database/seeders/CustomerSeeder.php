<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final class CustomerSeeder extends Seeder
{
    public const string EMAIL = 'cliente@sodaya.test';

    /** Seed a demo customer, for local development only, until customers can sign up. */
    public function run(): void
    {
        UserModel::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Marta Solís',
                'password' => Hash::make(StaffSeeder::PASSWORD),
                'role' => Role::Customer->value,
                'soda_id' => null,
            ],
        );
    }
}
