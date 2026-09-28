<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Agrégalo a cualquier modelo (`use AuditsDeletion;`) y cada eliminación
 * deja una fila en audit_logs con quién lo eliminó y los valores que tenía,
 * en JSON.
 *
 * Ojo: solo funciona con eliminaciones a nivel de modelo ($model->delete()).
 * Las masivas por query builder (Model::where(...)->delete()) no disparan
 * eventos y no se auditan. Con SoftDeletes también registra el borrado lógico.
 */
trait AuditsDeletion
{
    protected static function bootAuditsDeletion(): void
    {
        static::deleted(function (Model $model) {
            AuditLog::create([
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'event' => 'deleted',
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name,
                'old_values' => $model->attributesToArray(),
            ]);
        });
    }
}
