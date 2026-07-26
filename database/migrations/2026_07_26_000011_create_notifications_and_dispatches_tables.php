<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 191);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id')->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notifications');
    }
};
