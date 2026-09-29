<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etiqueta de cada foto (ej. "Anverso", "Reverso", "Sello") y quién la subió.
     */
    public function up(): void
    {
        Schema::table('rental_media', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('type');
            $table->foreignId('user_id')->nullable()->after('rental_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('label');
        });
    }
};
