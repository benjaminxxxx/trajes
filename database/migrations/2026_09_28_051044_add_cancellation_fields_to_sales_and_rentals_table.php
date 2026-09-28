<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['sales', 'rentals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['sales', 'rentals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('cancelled_by');
                $table->dropColumn(['cancelled_at', 'cancellation_reason']);
            });
        }
    }
};
