<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE document_transactions MODIFY action ENUM(
                'submit_by_unit',
                'receive_at_managing_office',
                'forward_to_upstream_office',
                'record_upstream_receipt',
                'return_from_upstream_office',
                'mark_for_distribution',
                'assign_recipient_unit',
                'place_in_receiving_box',
                'claim_by_recipient_unit',
                'cancel'
            ) NOT NULL");
        }

        if (! Schema::hasColumn('document_transactions', 'receiver_name')) {
            Schema::table('document_transactions', function (Blueprint $table) {
                $table->string('receiver_name')->nullable()->after('remarks');
                $table->string('receiver_position')->nullable()->after('receiver_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('document_transactions', function (Blueprint $table) {
            $table->dropColumn(['receiver_name', 'receiver_position']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE document_transactions MODIFY action ENUM(
                'submit_by_unit',
                'receive_at_managing_office',
                'forward_to_upstream_office',
                'record_upstream_receipt',
                'return_from_upstream_office',
                'mark_for_distribution',
                'assign_recipient_unit',
                'place_in_receiving_box',
                'cancel'
            ) NOT NULL");
        }
    }
};
