<?php

namespace Database\Seeders;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demoPassword = config('drms.demo_password');

        if (! app()->environment(['local', 'testing']) || blank($demoPassword)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@drms.local'],
            [
                'organizational_unit_id' => null,
                'full_name' => 'DRMS Records Administrator',
                'position' => 'Records Administrator',
                'username' => 'admin',
                'password' => $demoPassword,
                'role' => UserRole::LevelTwo,
                'status' => OperationalStatus::Active,
            ],
        );
    }
}
