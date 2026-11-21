<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDay extends Model
{
    protected $table = 'maintenance_days';

    public const PENDIENTE = 'PENDIENTE';

    public const REALIZADO = 'REALIZADO';

    public const NO_REALIZADO = 'NO_REALIZADO';

    public const ESTADOS = [self::PENDIENTE, self::REALIZADO, self::NO_REALIZADO];

    protected $fillable = ['schedule_id', 'date', 'observation', 'image_path', 'status'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }
}
