<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('organizational_units')
                ->restrictOnDelete();
            $table->enum('unit_type', ['school', 'district', 'division', 'section', 'functional_unit']);
            $table->string('unit_code', 50)->unique();
            $table->string('unit_name');
            $table->string('short_name')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['unit_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
