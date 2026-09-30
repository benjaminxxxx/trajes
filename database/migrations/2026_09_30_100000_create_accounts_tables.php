<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas (negocios) y la pertenencia de cada persona a ellas.
     *
     * - accounts.owner_id: el dueño vive en la cuenta, no en cada tabla. Cambiar
     *   de dueño es actualizar una sola fila.
     * - account_user: una persona (users, correo único global) puede trabajar en
     *   varios negocios. Retirarla de uno no la borra ni afecta a los demás.
     * - users.current_account_id: el último negocio en el que trabajó.
     *
     * Si ya hay datos (instalación existente), se crea la primera cuenta con los
     * datos de config/business.php y todos los usuarios pasan a ser sus miembros.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ruc', 11)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('receipt_footer')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('status', 20)->default('active'); // trial, active, suspended, blocked
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active, revoked
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_account_id')->nullable()->after('id')->constrained('accounts')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
        });

        $this->migrateExistingInstallation();
    }

    protected function migrateExistingInstallation(): void
    {
        if (! DB::table('users')->exists()) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $ownerId = DB::table('model_has_roles')
            ->where('role_id', $adminRoleId)
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->whereNull('users.deleted_at')
            ->orderBy('users.id')
            ->value('users.id')
            ?? DB::table('users')->orderBy('id')->value('id');

        $accountId = DB::table('accounts')->insertGetId([
            'name' => config('business.name', 'Mi negocio'),
            'ruc' => config('business.ruc'),
            'address' => config('business.address'),
            'phone' => config('business.phone'),
            'receipt_footer' => config('business.receipt_footer'),
            'owner_id' => $ownerId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('users')->get(['id', 'deleted_at']) as $user) {
            DB::table('account_user')->insert([
                'account_id' => $accountId,
                'user_id' => $user->id,
                // Los usuarios dados de baja pasan a estar retirados del negocio.
                'status' => $user->deleted_at ? 'revoked' : 'active',
                'revoked_at' => $user->deleted_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->update(['current_account_id' => $accountId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_account_id');
            $table->dropColumn('is_platform_admin');
        });

        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
