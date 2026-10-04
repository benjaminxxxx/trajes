<?php

namespace App\Models;

use App\Services\Core\CurrentAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Agrégalo a un modelo (`use AuditsChanges;`) y cada edición y eliminación
 * queda en audit_logs: quién, cuándo, qué había y qué quedó. Se consulta en
 * Administración → Auditoría.
 *
 * - Solo se guardan los campos que cambiaron; los ocultos del modelo
 *   ($hidden: tokens, contraseñas) se registran enmascarados.
 * - `auditExcluded()` deja fuera campos que cambian solos por el sistema
 *   (stock, contadores) y ya tienen su propio historial.
 * - Las ediciones masivas por query builder no disparan eventos y no se auditan.
 */
trait AuditsChanges
{
    use AuditsDeletion;

    protected static function bootAuditsChanges(): void
    {
        static::updated(function (Model $model) {
            $ignored = ['created_at', 'updated_at', 'deleted_at', ...$model->auditExcluded()];
            $changed = array_diff(array_keys($model->getChanges()), $ignored);

            if ($changed === []) {
                return;
            }

            $mask = fn (string $key, mixed $value) => in_array($key, $model->getHidden(), true) ? '••••' : $value;
            $old = [];
            $new = [];

            foreach ($changed as $key) {
                $old[$key] = $mask($key, $model->getRawOriginal($key));
                $new[$key] = $mask($key, $model->getAttributes()[$key] ?? null);
            }

            AuditLog::create([
                'account_id' => $model->getAttribute('account_id') ?? app(CurrentAccount::class)->id(),
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'event' => 'updated',
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name,
                'old_values' => $old,
                'new_values' => $new,
            ]);
        });
    }

    /**
     * Campos que no se auditan al editar.
     *
     * @return array<int, string>
     */
    public function auditExcluded(): array
    {
        return [];
    }
}
