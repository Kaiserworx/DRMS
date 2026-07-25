<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('deployment_profile', ['district', 'division', 'regional']);
            $table->string('system_name');
            $table->string('managing_office_name');
            $table->string('managing_office_code', 50);
            $table->enum('managing_office_level', ['district', 'division', 'regional']);
            $table->string('upstream_office_label');
            $table->string('level_1_unit_label');
            $table->string('receiving_box_label');
            $table->string('tracking_prefix', 20);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_settings');
    }
};
