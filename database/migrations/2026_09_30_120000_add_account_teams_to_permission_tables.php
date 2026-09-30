<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles por cuenta (función "teams" de spatie/laravel-permission, con
     * account_id como clave). Los roles admin y vendedor son globales
     * (roles.account_id = null); lo que es por cuenta es la asignación: una
     * persona puede ser admin en un negocio y vendedor en otro.
     *
     * Basado en add_teams_fields.php.stub del paquete. En una base nueva la
     * migración de permisos ya crea estas columnas y esta no hace nada.
     */
    public function up(): void
    {
        $accountId = DB::table('accounts')->min('id') ?? 1;
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasColumn('roles', 'account_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id');
                $table->index('account_id', 'roles_team_foreign_key_index');
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique(['account_id', 'name', 'guard_name']);
            });
        }

        foreach (['model_has_permissions' => ['permission_id', 'permissions'], 'model_has_roles' => ['role_id', 'roles']] as $tableName => [$pivotKey, $pivotTable]) {
            if (Schema::hasColumn($tableName, 'account_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $pivotKey, $pivotTable, $accountId, $isSqlite) {
                $table->unsignedBigInteger('account_id')->default($accountId);
                $table->index('account_id', "{$tableName}_team_foreign_key_index");

                if (! $isSqlite) {
                    $table->dropForeign([$pivotKey]);
                }

                $table->dropPrimary();
                $table->primary(['account_id', $pivotKey, 'model_id', 'model_type'], $tableName === 'model_has_roles'
                    ? 'model_has_roles_role_model_type_primary'
                    : 'model_has_permissions_permission_model_type_primary');

                if (! $isSqlite) {
                    $table->foreign($pivotKey)->references('id')->on($pivotTable)->cascadeOnDelete();
                }
            });
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        //
    }
};
