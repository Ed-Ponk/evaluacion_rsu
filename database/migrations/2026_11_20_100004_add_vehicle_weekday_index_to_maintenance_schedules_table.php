<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $tabla) {
            $tabla->index(['vehicle_id', 'weekday'], 'maintenance_schedules_vehicle_weekday_index');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $tabla) {
            $tabla->dropIndex('maintenance_schedules_vehicle_weekday_index');
        });
    }
};
