<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_days', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('schedule_id')->constrained('maintenance_schedules')->cascadeOnDelete();
            $tabla->date('date');
            $tabla->text('observation')->nullable();
            $tabla->string('image_path', 1000)->nullable();
            $tabla->string('status', 14)->default('PENDIENTE');
            $tabla->timestamps();

            $tabla->unique(['schedule_id', 'date'], 'maintenance_days_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_days');
    }
};
