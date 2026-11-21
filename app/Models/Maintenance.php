<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Maintenance extends Model
{
    protected $table = 'maintenances';

    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'maintenance_id');
    }

    public function horarios(): HasMany
    {
        return $this->schedules();
    }

    public function dias(): HasManyThrough
    {
        return $this->hasManyThrough(MaintenanceDay::class, MaintenanceSchedule::class, 'maintenance_id', 'schedule_id');
    }
}
