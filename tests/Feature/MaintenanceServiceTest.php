<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Models\ColorVehiculo;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\MaintenanceService;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceService $servicio;

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
        $this->servicio = new MaintenanceService;
    }

    private function vehiculo(): Vehiculo
    {
        $this->contador++;
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-VOL']);

        return Vehiculo::create([
            'name' => 'Vehículo '.$this->contador,
            'code' => 'VEH-'.$this->contador,
            'plate' => 'AB'.str_pad((string) $this->contador, 4, '0', STR_PAD_LEFT),
            'year' => 2022,
            'load_capacity' => 8.5,
            'fuel_capacity' => 120,
            'compact_capacity' => 6,
            'occupant_capacity' => 3,
            'status' => true,
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'type_id' => TipoVehiculo::firstOrCreate(['name' => 'Compactador'])->id,
            'color_id' => ColorVehiculo::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF'])->id,
        ]);
    }

    private function persona(): Personal
    {
        $this->contador++;
        $dni = (string) (40000000 + $this->contador);

        return Personal::create([
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Responsable',
            'last_name' => 'N'.$dni,
            'birthdate' => '1990-01-01',
            'email' => "responsable{$dni}@prueba.test",
            'address' => 'Av. Principal 123',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function mantenimiento(string $inicio = '2026-12-01', string $fin = '2026-12-31'): Maintenance
    {
        $this->contador++;

        return $this->servicio->crearMantenimiento([
            'name' => 'Mantenimiento '.$this->contador,
            'start_date' => $inicio,
            'end_date' => $fin,
        ]);
    }

    private function horario(Maintenance $mantenimiento, array $cambios = []): MaintenanceSchedule
    {
        return $this->servicio->crearHorario($mantenimiento, $cambios + [
            'vehicle_id' => $this->vehiculo()->id,
            'responsible_id' => $this->persona()->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
    }

    private function datosHorario(MaintenanceSchedule $horario, array $cambios = []): array
    {
        return $cambios + [
            'vehicle_id' => $horario->vehicle_id,
            'responsible_id' => $horario->responsible_id,
            'type' => $horario->type,
            'weekday' => $horario->weekday,
            'start_time' => $horario->start_time,
            'end_time' => $horario->end_time,
        ];
    }

    private function fechas(MaintenanceSchedule $horario): array
    {
        return $horario->dias()->orderBy('date')->pluck('date')
            ->map(fn ($fecha) => $fecha->toDateString())->all();
    }

    private function guardarArchivoReal(): string
    {
        $subida = new class
        {
            use RespondeAjax;

            public function guardar(UploadedFile $archivo, string $carpeta): string
            {
                return $this->guardarArchivo($archivo, $carpeta);
            }
        };

        return $subida->guardar(UploadedFile::fake()->image('evidencia.jpg'), 'maintenance_days');
    }

    private function rechazo(callable $accion, string $campo): string
    {
        try {
            $accion();
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($campo, $error->errors());

            return $error->errors()[$campo][0];
        }

        $this->fail('Se esperaba un rechazo de validación en '.$campo);
    }

    public function test_la_fecha_de_inicio_no_puede_ser_posterior_a_la_de_fin(): void
    {
        $this->rechazo(fn () => $this->servicio->crearMantenimiento([
            'name' => 'Invertido', 'start_date' => '2026-12-31', 'end_date' => '2026-12-01',
        ]), 'start_date');

        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_los_rangos_de_mantenimiento_no_se_solapan_ni_en_el_dia_compartido(): void
    {
        $this->mantenimiento('2026-12-01', '2026-12-31');

        $rangos = [
            ['2026-12-01', '2026-12-31'],
            ['2026-12-10', '2026-12-20'],
            ['2026-11-20', '2026-12-05'],
            ['2026-12-25', '2027-01-10'],
            ['2026-12-31', '2027-01-05'],
        ];

        foreach ($rangos as [$inicio, $fin]) {
            $this->rechazo(fn () => $this->servicio->crearMantenimiento([
                'name' => 'Solapado', 'start_date' => $inicio, 'end_date' => $fin,
            ]), 'start_date');
        }

        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_mantenimientos_contiguos_y_editar_el_propio_son_permitidos(): void
    {
        $this->mantenimiento('2026-12-01', '2026-12-15');
        $otro = $this->mantenimiento('2026-12-16', '2026-12-31');

        $editado = $this->servicio->actualizarMantenimiento($otro, [
            'name' => 'Editado', 'start_date' => '2026-12-16', 'end_date' => '2026-12-31',
        ]);

        $this->assertSame('Editado', $editado->fresh()->name);
        $this->assertDatabaseCount('maintenances', 2);
    }

    public function test_ampiar_el_rango_agrega_dias_pendientes_a_cada_horario(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-15');
        $horario = $this->horario($mantenimiento, ['weekday' => 1]);

        $this->assertSame(['2026-12-07', '2026-12-14'], $this->fechas($horario));

        $this->servicio->actualizarMantenimiento($mantenimiento, [
            'name' => 'Diciembre completo', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        ]);

        $this->assertSame([
            '2026-12-07', '2026-12-14', '2026-12-21', '2026-12-28',
        ], $this->fechas($horario->fresh()));
    }

    public function test_un_dia_pendiente_con_imagen_sobrevive_al_ampiar_el_rango(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-15');
        $horario = $this->horario($mantenimiento, ['weekday' => 1]);
        $dia = $horario->dias()->orderBy('date')->first();
        $ruta = $this->guardarArchivoReal();
        $dia->update(['image_path' => $ruta]);

        $this->servicio->actualizarMantenimiento($mantenimiento, [
            'name' => 'Diciembre completo', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        ]);

        $this->assertFileExists(public_path(ltrim($ruta, '/')));
        $this->assertDatabaseHas('maintenance_days', [
            'id' => $dia->id, 'date' => '2026-12-07', 'status' => MaintenanceDay::PENDIENTE, 'image_path' => $ruta,
        ]);
        $this->assertSame(4, $horario->dias()->count());
    }

    public function test_un_dia_pendiente_con_observacion_sobrevive_al_ampiar_el_rango(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-15');
        $horario = $this->horario($mantenimiento, ['weekday' => 1]);
        $dia = $horario->dias()->orderBy('date')->first();
        $dia->update(['observation' => 'Revisar correa']);

        $this->servicio->actualizarMantenimiento($mantenimiento, [
            'name' => 'Diciembre completo', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('maintenance_days', [
            'id' => $dia->id, 'date' => '2026-12-07', 'status' => MaintenanceDay::PENDIENTE, 'observation' => 'Revisar correa',
        ]);
        $this->assertSame(4, $horario->dias()->count());
    }

    public function test_acortar_el_rango_sin_evidencia_borra_los_pendientes_fuera(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-31');
        $horario = $this->horario($mantenimiento, ['weekday' => 1]);

        $this->assertCount(4, $this->fechas($horario));

        $this->servicio->actualizarMantenimiento($mantenimiento, [
            'name' => 'Solo quincena', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15',
        ]);

        $this->assertSame(['2026-12-07', '2026-12-14'], $this->fechas($horario->fresh()));
        $this->assertDatabaseMissing('maintenance_days', ['schedule_id' => $horario->id, 'date' => '2026-12-28']);
    }

    public function test_acortar_el_rango_con_evidencia_fuera_se_rechaza(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-31');
        $horario = $this->horario($mantenimiento, ['weekday' => 1]);
        $dia = $horario->dias()->where('date', '2026-12-28')->first();
        $cambios = [
            'name' => 'Solo quincena', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15',
        ];

        $dia->update(['status' => MaintenanceDay::REALIZADO]);

        $mensaje = $this->rechazo(fn () => $this->servicio->actualizarMantenimiento($mantenimiento, $cambios), 'maintenance');

        $this->assertStringContainsString('2026-12-28', $mensaje);
        $this->assertSame('2026-12-31', $mantenimiento->fresh()->end_date->toDateString());

        $dia->update(['status' => MaintenanceDay::PENDIENTE, 'image_path' => '/uploads/ejemplo.jpg']);

        $this->rechazo(fn () => $this->servicio->actualizarMantenimiento($mantenimiento, $cambios), 'maintenance');

        $dia->update(['image_path' => null, 'observation' => 'Pendiente de revisión']);

        $this->rechazo(fn () => $this->servicio->actualizarMantenimiento($mantenimiento, $cambios), 'maintenance');

        $this->assertDatabaseHas('maintenance_days', ['id' => $dia->id, 'date' => '2026-12-28']);
    }

    public function test_horarios_del_mismo_vehiculo_y_dia_no_se_solapan(): void
    {
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $this->horario($mantenimiento, ['vehicle_id' => $vehiculo->id]);

        foreach ([['08:00', '10:00'], ['08:30', '09:30'], ['07:00', '08:30'], ['09:30', '11:00']] as [$inicio, $fin]) {
            $this->rechazo(fn () => $this->horario($mantenimiento, [
                'vehicle_id' => $vehiculo->id, 'start_time' => $inicio, 'end_time' => $fin,
            ]), 'start_time');
        }

        $this->assertSame(1, $mantenimiento->schedules()->count());
    }

    public function test_el_mismo_horario_en_mantenimientos_distintos_es_permitido(): void
    {
        $diciembre = $this->mantenimiento('2026-12-01', '2026-12-31');
        $enero = $this->mantenimiento('2027-01-01', '2027-01-31');
        $vehiculo = $this->vehiculo();

        $this->horario($diciembre, ['vehicle_id' => $vehiculo->id]);
        $esteEnero = $this->horario($enero, ['vehicle_id' => $vehiculo->id]);

        $this->assertSame($vehiculo->id, $esteEnero->vehicle_id);
        $this->assertSame(2, MaintenanceSchedule::count());

        $this->rechazo(fn () => $this->horario($diciembre, ['vehicle_id' => $vehiculo->id]), 'start_time');
    }

    public function test_horarios_contiguos_otro_vehiculo_y_otro_dia_son_permitidos(): void
    {
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $this->horario($mantenimiento, ['vehicle_id' => $vehiculo->id]);

        $this->horario($mantenimiento, ['vehicle_id' => $vehiculo->id, 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->horario($mantenimiento, ['vehicle_id' => $this->vehiculo()->id]);
        $this->horario($mantenimiento, ['vehicle_id' => $vehiculo->id, 'weekday' => 2]);

        $this->assertSame(4, $mantenimiento->schedules()->count());
    }

    public function test_editar_un_horario_no_choca_consigo_mismo_y_normaliza_las_horas(): void
    {
        $horario = $this->horario($this->mantenimiento());

        $editado = $this->servicio->actualizarHorario($horario, $this->datosHorario($horario, [
            'type' => MaintenanceSchedule::LIMPIEZA,
            'start_time' => '8:00',
            'end_time' => '10:00:00',
        ]));

        $this->assertSame('08:00', $editado->start_time);
        $this->assertSame('10:00', $editado->end_time);
        $this->assertSame(MaintenanceSchedule::LIMPIEZA, $editado->type);
    }

    public function test_la_hora_de_inicio_debe_ser_menor_que_la_de_fin(): void
    {
        $this->rechazo(fn () => $this->horario($this->mantenimiento(), [
            'start_time' => '10:00', 'end_time' => '10:00',
        ]), 'end_time');
    }

    public function test_el_responsable_debe_ser_personal_y_no_un_administrador(): void
    {
        $administrador = User::factory()->create();

        $this->rechazo(fn () => $this->horario($this->mantenimiento(), [
            'responsible_id' => $administrador->id,
        ]), 'responsible_id');
    }

    public function test_el_vehiculo_debe_estar_activo(): void
    {
        $vehiculo = $this->vehiculo();
        $vehiculo->update(['status' => false]);

        $mensaje = $this->rechazo(fn () => $this->horario($this->mantenimiento(), [
            'vehicle_id' => $vehiculo->id,
        ]), 'vehicle_id');

        $this->assertStringContainsString('no está activo', $mensaje);
    }

    public function test_genera_los_dias_con_day_of_week_iso(): void
    {
        $mantenimiento = $this->mantenimiento('2026-12-01', '2026-12-31');

        $lunes = $this->horario($mantenimiento, ['weekday' => 1]);
        $martes = $this->horario($mantenimiento, [
            'weekday' => 2, 'start_time' => '10:00', 'end_time' => '11:00',
        ]);

        $this->assertSame(['2026-12-07', '2026-12-14', '2026-12-21', '2026-12-28'], $this->fechas($lunes));
        $this->assertSame(['2026-12-01', '2026-12-08', '2026-12-15', '2026-12-22', '2026-12-29'], $this->fechas($martes));
        $this->assertDatabaseHas('maintenance_days', ['schedule_id' => $lunes->id, 'date' => '2026-12-07', 'status' => MaintenanceDay::PENDIENTE]);
    }

    public function test_cambiar_el_dia_con_evidencia_se_rechaza(): void
    {
        $horario = $this->horario($this->mantenimiento(), ['weekday' => 1]);
        $dia = $horario->dias()->orderBy('date')->first();

        foreach ([MaintenanceDay::REALIZADO, MaintenanceDay::NO_REALIZADO] as $estado) {
            $dia->update(['status' => $estado, 'image_path' => null]);

            $this->rechazo(fn () => $this->servicio->actualizarHorario(
                $horario, $this->datosHorario($horario, ['weekday' => 2])
            ), 'weekday');
        }

        $dia->update(['status' => MaintenanceDay::PENDIENTE, 'image_path' => '/uploads/ejemplo.jpg']);

        $this->rechazo(fn () => $this->servicio->actualizarHorario(
            $horario, $this->datosHorario($horario, ['weekday' => 2])
        ), 'weekday');

        $this->assertSame(1, (int) $horario->fresh()->weekday);
    }

    public function test_cambiar_solo_el_vehiculo_no_altera_los_dias(): void
    {
        $horario = $this->horario($this->mantenimiento(), ['weekday' => 1]);
        $ids = $horario->dias()->orderBy('date')->pluck('id')->all();
        $horario->dias()->orderBy('date')->first()->update([
            'status' => MaintenanceDay::NO_REALIZADO,
            'observation' => 'No se pudo hacer',
        ]);

        $this->servicio->actualizarHorario($horario, $this->datosHorario($horario, [
            'vehicle_id' => $this->vehiculo()->id,
        ]));

        $this->assertSame($ids, $horario->dias()->orderBy('date')->pluck('id')->all());
        $this->assertDatabaseHas('maintenance_days', [
            'id' => $ids[0], 'date' => '2026-12-07', 'status' => MaintenanceDay::NO_REALIZADO,
            'observation' => 'No se pudo hacer',
        ]);
        $this->assertSame(4, $horario->dias()->count());
    }

    public function test_no_se_puede_eliminar_un_horario_con_dias_realizados(): void
    {
        $horario = $this->horario($this->mantenimiento());
        $horario->dias()->first()->update(['status' => MaintenanceDay::REALIZADO]);

        $this->rechazo(fn () => $this->servicio->eliminarHorario($horario), 'schedule');

        $this->assertDatabaseHas('maintenance_schedules', ['id' => $horario->id]);
    }

    public function test_eliminar_un_horario_borra_la_imagen_de_guardar_archivo_y_rechaza_traversal(): void
    {
        $ruta = $this->guardarArchivoReal();
        $this->assertStringStartsWith('/uploads/maintenance_days/', $ruta);

        $fisica = public_path(ltrim($ruta, '/'));
        $this->assertFileExists($fisica);

        $horario = $this->horario($this->mantenimiento());
        $horario->dias()->first()->update(['image_path' => $ruta]);
        $this->servicio->eliminarHorario($horario);
        $this->assertFileDoesNotExist($fisica);

        $rutaMala = '/uploads/maintenance_days/../secreto.jpg';
        $fisicaMala = public_path('uploads/secreto.jpg');
        File::ensureDirectoryExists(dirname($fisicaMala));
        File::put($fisicaMala, 'secreto');

        try {
            $otro = $this->horario($this->mantenimiento('2027-01-01', '2027-01-31'));
            $otro->dias()->first()->update(['image_path' => $rutaMala]);
            $this->servicio->eliminarHorario($otro);
            $this->assertFileExists($fisicaMala);
        } finally {
            File::delete($fisicaMala);
        }
    }

    public function test_eliminar_un_horario_borra_sus_dias_y_su_imagen_fisica(): void
    {
        $horario = $this->horario($this->mantenimiento());
        $dia = $horario->dias()->first();
        $ruta = '/uploads/maintenance_days/'.$dia->id.'/prueba.jpg';
        $fisica = public_path(ltrim($ruta, '/'));

        File::ensureDirectoryExists(dirname($fisica));
        File::put($fisica, 'contenido de prueba');
        $dia->update(['image_path' => $ruta]);

        try {
            $this->servicio->eliminarHorario($horario);

            $this->assertFileDoesNotExist($fisica);
            $this->assertDatabaseMissing('maintenance_days', ['schedule_id' => $horario->id]);
            $this->assertDatabaseMissing('maintenance_schedules', ['id' => $horario->id]);
        } finally {
            File::deleteDirectory(dirname($fisica));
        }
    }

    public function test_no_se_puede_eliminar_un_mantenimiento_con_dias_realizados(): void
    {
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);
        $horario->dias()->first()->update(['status' => MaintenanceDay::REALIZADO]);

        $this->rechazo(fn () => $this->servicio->eliminarMantenimiento($mantenimiento), 'maintenance');

        $this->assertDatabaseHas('maintenances', ['id' => $mantenimiento->id]);
    }

    public function test_eliminar_un_mantenimiento_borra_horarios_y_dias_en_cascada(): void
    {
        $mantenimiento = $this->mantenimiento();
        $this->horario($mantenimiento);
        $this->horario($mantenimiento, ['weekday' => 2]);

        $this->assertDatabaseCount('maintenance_schedules', 2);
        $this->assertDatabaseCount('maintenance_days', 9);

        $this->servicio->eliminarMantenimiento($mantenimiento);

        $this->assertDatabaseMissing('maintenances', ['id' => $mantenimiento->id]);
        $this->assertDatabaseCount('maintenance_schedules', 0);
        $this->assertDatabaseCount('maintenance_days', 0);
    }
}
