<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('document_recipients')->restrictOnDelete();
            $table->enum('action', [
                'submit_by_unit',
                'receive_at_managing_office',
                'forward_to_upstream_office',
                'record_upstream_receipt',
                'return_from_upstream_office',
                'mark_for_distribution',
                'assign_recipient_unit',
                'place_in_receiving_box',
                'cancel',
            ]);
            $table->enum('previous_status', [
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
            ]);
            $table->enum('new_status', [
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
            ]);
            $table->enum('from_location', [
                'organizational_unit',
                'managing_office',
                'upstream_office',
                'receiving_box',
                'recipient_unit',
            ]);
            $table->enum('to_location', [
                'organizational_unit',
                'managing_office',
                'upstream_office',
                'receiving_box',
                'recipient_unit',
            ]);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('transaction_date');
            $table->text('remarks')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('device_info')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'transaction_date']);
            $table->index(['recipient_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transactions');
    }
};
