<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')
                ->nullable()
                ->after('id')
                ->constrained('organizational_units')
                ->restrictOnDelete();
            $table->string('position')->nullable()->after('full_name');
            $table->string('username')->nullable()->unique()->after('email');
            $table->enum('role', ['level_1', 'level_2'])->after('password');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('role');
            $table->timestamp('last_login_at')->nullable()->after('status');

            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
            $table->dropIndex(['role', 'status']);
            $table->dropColumn([
                'organizational_unit_id',
                'position',
                'username',
                'role',
                'status',
                'last_login_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
        });
    }
};
