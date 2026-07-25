<?php

namespace App\Models;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeploymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'deployment_profile',
        'system_name',
        'managing_office_name',
        'managing_office_code',
        'managing_office_level',
        'upstream_office_label',
        'level_1_unit_label',
        'receiving_box_label',
        'tracking_prefix',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'deployment_profile' => DeploymentProfile::District,
            'system_name' => 'District Records Management System',
            'managing_office_name' => 'District Office',
            'managing_office_code' => 'DISTRICT',
            'managing_office_level' => DeploymentProfile::District,
            'upstream_office_label' => 'Division Office',
            'level_1_unit_label' => 'School',
            'receiving_box_label' => 'School Box',
            'tracking_prefix' => 'DRMS',
            'status' => OperationalStatus::Active,
        ];
    }

    public static function current(): ?self
    {
        return self::query()->first();
    }

    protected function casts(): array
    {
        return [
            'deployment_profile' => DeploymentProfile::class,
            'managing_office_level' => DeploymentProfile::class,
            'status' => OperationalStatus::class,
        ];
    }
}
