<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $dirty);
            AuditLog::record('updated', $model, $old, $dirty);
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->getOriginal(), null);
        });
    }
}

