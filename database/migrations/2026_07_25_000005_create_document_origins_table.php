<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_origins', function (Blueprint $table) {
            $table->id();
            $table->enum('origin_type', [
                'organizational_unit',
                'managing_office',
                'upstream_office',
                'external',
            ]);
            $table->string('origin_name');
            $table->string('normalized_name');
            $table->string('office_code', 50)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['origin_type', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_origins');
    }
};
