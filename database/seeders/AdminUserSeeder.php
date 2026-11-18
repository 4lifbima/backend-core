<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator / pemateri account.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@uploadkarya.id');
        $password = env('ADMIN_PASSWORD', 'Admin1234!');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Pemateri Desain',
                'password' => $password,
            ],
        );
    }
}
