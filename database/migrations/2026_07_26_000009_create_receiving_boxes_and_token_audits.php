<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')
                ->unique()
                ->constrained('organizational_units')
                ->restrictOnDelete();
            $table->string('qr_token', 64)->unique();
            $table->string('box_location')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->dateTime('last_qr_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_box_token_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_box_id')->constrained()->restrictOnDelete();
            $table->enum('action', ['generated', 'regenerated']);
            $table->char('previous_token_fingerprint', 64)->nullable();
            $table->char('new_token_fingerprint', 64);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->ipAddress('ip_address')->nullable();
            $table->text('device_info')->nullable();
            $table->timestamps();

            $table->index(['receiving_box_id', 'occurred_at']);
        });

        Schema::table('document_recipients', function (Blueprint $table) {
            $table->foreign('receiving_box_id')
                ->references('id')
                ->on('receiving_boxes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_recipients', function (Blueprint $table) {
            $table->dropForeign(['receiving_box_id']);
        });

        Schema::dropIfExists('receiving_box_token_audits');
        Schema::dropIfExists('receiving_boxes');
    }
};
