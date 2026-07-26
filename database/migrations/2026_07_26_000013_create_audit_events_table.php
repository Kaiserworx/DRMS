<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 100);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('auditable_type', 150)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('details')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('device_info')->nullable();
            $table->dateTime('occurred_at');

            $table->index(['event_type', 'occurred_at']);
            $table->index(
                ['auditable_type', 'auditable_id', 'occurred_at'],
                'audit_subject_time_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
