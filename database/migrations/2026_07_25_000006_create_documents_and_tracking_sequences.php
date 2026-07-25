<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 20);
            $table->string('office_code', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'office_code', 'year']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no')->unique();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->foreignId('origin_id')->nullable()->constrained('document_origins')->restrictOnDelete();
            $table->string('origin_reference_no')->nullable();
            $table->foreignId('submitting_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('priority', ['normal', 'urgent'])->default('normal');
            $table->enum('current_status', [
                'draft',
                'submitted',
                'received_at_managing_office',
                'forwarded_to_upstream_office',
                'received_at_upstream_office',
                'returned_from_upstream_office',
                'for_distribution',
                'ready_for_pickup',
                'partially_claimed',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->enum('current_location', [
                'organizational_unit',
                'managing_office',
                'upstream_office',
                'receiving_box',
                'recipient_unit',
            ]);
            $table->dateTime('date_received')->nullable();
            $table->date('due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['submitting_unit_id', 'current_status']);
            $table->index(['subject', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('tracking_sequences');
    }
};
