<?php

namespace Database\Seeders;

use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Enums\UserRole;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DocumentTypeSeeder::class);

        $settings = DeploymentSetting::query()->firstOrCreate(
            ['id' => 1],
            DeploymentSetting::defaults(),
        );

        $unit = OrganizationalUnit::query()->firstOrCreate(
            ['unit_code' => 'SCHOOL-001'],
            [
                'parent_id' => null,
                'unit_type' => OrganizationalUnitType::School,
                'unit_name' => 'Pilot Elementary School',
                'short_name' => 'PES',
                'status' => OperationalStatus::Active,
            ],
        );

        $demoPassword = config('drms.demo_password');

        if (! app()->environment(['local', 'testing']) || blank($demoPassword)) {
            return;
        }

        $this->call(AdminUserSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'encoder@drms.local'],
            [
                'organizational_unit_id' => $unit->getKey(),
                'full_name' => "{$settings->level_1_unit_label} Encoder",
                'position' => 'Records Encoder',
                'username' => 'encoder',
                'password' => $demoPassword,
                'role' => UserRole::LevelOne,
                'status' => OperationalStatus::Active,
            ],
        );
    }
}
