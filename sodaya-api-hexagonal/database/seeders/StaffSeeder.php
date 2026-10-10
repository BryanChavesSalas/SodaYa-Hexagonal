<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final class StaffSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    /**
     * Fictitious staff of the demo soda: email, name and role.
     *
     * @var list<array{string, string, Role}>
     */
    private const array STAFF = [
        ['duena@sodaya.test', 'Ana Mora', Role::Owner],
        ['cocina@sodaya.test', 'Luis Rojas', Role::Kitchen],
    ];

    /** Seed the owner and the kitchen accounts of the demo soda, for local development only. */
    public function run(): void
    {
        foreach (self::STAFF as [$email, $name, $role]) {
            UserModel::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $role->value,
                    'soda_id' => CatalogSeeder::DEMO_SODA_ID,
                ],
            );
        }
    }
}
