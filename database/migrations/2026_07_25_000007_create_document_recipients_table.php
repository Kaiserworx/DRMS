<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->unsignedBigInteger('receiving_box_id')->nullable();
            $table->enum('recipient_status', [
                'assigned',
                'ready_for_pickup',
                'received_by_recipient_unit',
            ])->default('assigned');
            $table->dateTime('date_assigned');
            $table->dateTime('date_placed')->nullable();
            $table->dateTime('date_claimed')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('received_by_name')->nullable();
            $table->string('received_by_position')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'recipient_unit_id']);
            $table->index(['recipient_unit_id', 'recipient_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_recipients');
    }
};
