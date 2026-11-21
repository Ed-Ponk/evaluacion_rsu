<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
            $tabla->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $tabla->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $tabla->string('type', 20);
            $tabla->unsignedTinyInteger('weekday');
            $tabla->time('start_time');
            $tabla->time('end_time');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
