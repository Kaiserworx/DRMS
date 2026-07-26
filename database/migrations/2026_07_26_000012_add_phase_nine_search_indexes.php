<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->index(['document_type_id', 'created_at'], 'documents_type_created_index');
            $table->index(['origin_id', 'created_at'], 'documents_origin_created_index');
            $table->index(['current_status', 'current_location', 'priority'], 'documents_state_location_priority_index');
            $table->index('current_location', 'documents_current_location_index');
            $table->index('created_at', 'documents_created_at_index');
            $table->index('date_received', 'documents_date_received_index');
        });

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->index(['recipient_status', 'date_placed'], 'recipients_status_placed_index');
            $table->index('date_claimed', 'recipients_date_claimed_index');
        });

        Schema::table('document_transactions', function (Blueprint $table): void {
            $table->index(['action', 'transaction_date'], 'transactions_action_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_action_date_index');
        });

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->dropIndex('recipients_status_placed_index');
            $table->dropIndex('recipients_date_claimed_index');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_type_created_index');
            $table->dropIndex('documents_origin_created_index');
            $table->dropIndex('documents_state_location_priority_index');
            $table->dropIndex('documents_current_location_index');
            $table->dropIndex('documents_created_at_index');
            $table->dropIndex('documents_date_received_index');
        });
    }
};
