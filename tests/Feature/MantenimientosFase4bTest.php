<?php

namespace Tests\Feature;

use App\Models\ColorVehiculo;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\PermisoDeUsuario;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\MaintenanceService;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MantenimientosFase4bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
    }

    private function vehiculo(): Vehiculo
    {
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-VOL']);

        return Vehiculo::create([
            'name' => 'Vehículo F4B',
            'code' => 'VEH-F4B'.random_int(1000, 9999),
            'plate' => 'F4B'.random_int(100, 999),
            'year' => 2023,
            'load_capacity' => 9.5,
            'fuel_capacity' => 130,
            'compact_capacity' => 7,
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
        $dni = (string) random_int(60000000, 69999999);

        return Personal::create([
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Técnico',
            'last_name' => 'Fase4b',
            'birthdate' => '1991-02-02',
            'email' => "f4b{$dni}@prueba.test",
            'address' => 'Av. Segunda 456',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function mantenimiento(): Maintenance
    {
        return (new MaintenanceService)->crearMantenimiento([
            'name' => 'Mantenimiento Fase 4b',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
        ]);
    }

    private function horario(Maintenance $mantenimiento, int $inicio = 8): MaintenanceSchedule
    {
        return (new MaintenanceService)->crearHorario($mantenimiento, [
            'vehicle_id' => $this->vehiculo()->id,
            'responsible_id' => $this->persona()->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => sprintf('%02d:00', $inicio),
            'end_time' => sprintf('%02d:00', $inicio + 2),
        ]);
    }

    public function test_la_pantalla_de_horarios_rendeza_el_titulo_y_la_tabla_de_la_fase_4b(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();

        $this->get(route('admin.mantenimientos.horarios.index', $mantenimiento))
            ->assertOk()
            ->assertViewIs('admin.mantenimientos.horarios.index')
            ->assertSee($mantenimiento->name.' / Horarios')
            ->assertSee('Agregar horario')
            ->assertDontSee('MANT.')
            ->assertSee('Gestión de vehículos')
            ->assertSee(route('admin.vehiculos.index'), false)
            ->assertSee('Mantenimiento')
            ->assertSee('modal-lg', false)
            ->assertDontSee('modal-xl', false)
            ->assertSee('Día</th>', false)
            ->assertSee('Vehículo</th>', false)
            ->assertSee('Responsable</th>', false)
            ->assertSee('Tipo</th>', false)
            ->assertSee('Hora inicio</th>', false)
            ->assertSee('Hora fin</th>', false)
            ->assertSee('Ver días</th>', false)
            ->assertSee('Editar</th>', false)
            ->assertSee('Eliminar</th>', false)
            ->assertSee('data-tads-modulo="mantenimientoHorarios"', false)
            ->assertSee('data-url-catalogos="'.route('admin.mantenimientos.catalogos').'"', false)
            ->assertSee('id="ventanaFormulario"', false);
    }

    public function test_la_pantalla_de_dias_rendeza_el_titulo_y_la_tabla_de_la_fase_4b(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);

        $this->get(route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]))
            ->assertOk()
            ->assertViewIs('admin.mantenimientos.horarios.show')
            ->assertSee($mantenimiento->name.' – Días programados')
            ->assertDontSee('MANT.')
            ->assertSee('Detalle del horario')
            ->assertSee('Horarios')
            ->assertSee('Gestión de vehículos')
            ->assertSee('Lunes')
            ->assertSee('Vehículo F4B')
            ->assertSee(route('admin.vehiculos.show', $horario->vehiculo), false)
            ->assertSee('Fecha</th>', false)
            ->assertSee('Observación</th>', false)
            ->assertSee('Imagen</th>', false)
            ->assertSee('Estado</th>', false)
            ->assertSee('Editar</th>', false)
            ->assertDontSee('Nuevo día')
            ->assertSee('data-tads-modulo="mantenimientoDias"', false)
            ->assertSee('data-url-dias="'.url('admin/mantenimientos/'.$mantenimiento->id.'/horarios/'.$horario->id.'/dias').'"', false)
            ->assertSee('id="ventanaFormulario"', false);
    }

    public function test_horarios_show_devuelve_el_horario_y_sus_dias_en_json_ajax(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);

        $this->getJson(route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]))
            ->assertOk()
            ->assertJsonStructure([
                'horario' => ['id', 'vehiculo', 'placa', 'responsable', 'tipo', 'weekday', 'inicio', 'fin', 'total_dias'],
                'dias' => [['id', 'fecha', 'estado', 'observacion', 'imagen']],
            ])
            ->assertJsonPath('horario.id', $horario->id)
            ->assertJsonPath('horario.inicio', '08:00');
    }

    public function test_dias_show_devuelve_el_dia_en_json_ajax(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);
        $dia = $horario->dias()->first();

        $this->getJson(route('admin.mantenimientos.dias.show', [$mantenimiento, $horario, $dia]))
            ->assertOk()
            ->assertJsonPath('dia.id', $dia->id)
            ->assertJsonPath('dia.fecha', $dia->date->toDateString())
            ->assertJsonStructure(['dia' => ['id', 'schedule_id', 'fecha', 'estado', 'observacion', 'imagen']]);
    }

    public function test_el_solape_de_horarios_llega_en_la_clave_start_time(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $responsable = $this->persona();

        $this->postJson(route('admin.mantenimientos.horarios.store', $mantenimiento), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])->assertOk();

        $this->postJson(route('admin.mantenimientos.horarios.store', $mantenimiento), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])->assertStatus(422)
            ->assertJsonPath('errors.start_time.0', 'Este mantenimiento ya tiene un horario que se cruza para ese vehículo y día (los contiguos sí son permitidos).');
    }

    public function test_eliminar_horario_con_dias_realizados_devuelve_422_en_schedule(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);
        $horario->dias()->first()->update(['status' => MaintenanceDay::REALIZADO]);

        $this->deleteJson(route('admin.mantenimientos.horarios.destroy', [$mantenimiento, $horario]))
            ->assertStatus(422)
            ->assertJsonPath('errors.schedule.0', 'No se puede eliminar: hay días marcados como REALIZADO.');
    }

    public function test_los_modulos_y_assets_de_la_fase_4b_estan_registrados(): void
    {
        $ubicaciones = array_column(config('adminlte.plugins.EstilosTads.files'), 'location');

        foreach (['js/tads/mantenimientos.js', 'js/tads/mantenimientos-horarios.js', 'js/tads/mantenimientos-dias.js'] as $archivo) {
            $this->assertContains($archivo, $ubicaciones);
            $this->assertFileExists(public_path($archivo));
        }

        $horarios = file_get_contents(public_path('js/tads/mantenimientos-horarios.js'));
        $this->assertStringContainsString('data-error-destino', $horarios);
        $this->assertStringContainsString('Swal.fire', $horarios);
        $this->assertStringContainsString('gestor.recargar()', $horarios);
        $this->assertStringContainsString("(tipo === 'sort' || tipo === 'type') ? fila.weekday", $horarios);
        $this->assertStringContainsString('(inactivo)', $horarios);
        $this->assertStringContainsString('if (fila.realizados > 0) {', $horarios);
        $this->assertStringContainsString("title: 'No se puede eliminar'", $horarios);
        $this->assertLessThan(
            strpos($horarios, 'showCancelButton: true'),
            strpos($horarios, 'if (fila.realizados > 0) {')
        );

        $dias = file_get_contents(public_path('js/tads/mantenimientos-dias.js'));
        $this->assertStringContainsString('tabla.ajax.reload(null, false)', $dias);
        $this->assertStringContainsString("ventanaEl.addEventListener('submit'", $dias);
        $this->assertStringContainsString("ventanaEl.addEventListener('hidden.bs.modal'", $dias);
        $this->assertStringContainsString('quitar.disabled = true', $dias);
        $this->assertStringContainsString('URL.createObjectURL', $dias);
        $this->assertStringContainsString('Si no lo marca, el día se guardará como No realizado.', $dias);
        $this->assertStringContainsString('id="diaFecha"', $dias);
        $this->assertStringContainsString('readonly', $dias);
        $this->assertStringContainsString('data-ampliar-imagen', $dias);
    }

    public function test_responsable_inactivo_igual_al_actual_no_bloquea_la_edicion(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $responsable = $this->persona();
        $horario = (new MaintenanceService)->crearHorario($mantenimiento, [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $responsable->update(['status' => false]);

        $this->putJson(route('admin.mantenimientos.horarios.update', [$mantenimiento, $horario]), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])->assertOk();

        $otroResponsable = $this->persona();
        $otroResponsable->update(['status' => false]);

        $this->putJson(route('admin.mantenimientos.horarios.update', [$mantenimiento, $horario]), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $otroResponsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ])->assertStatus(422)
            ->assertJsonPath('errors.responsible_id.0', 'El responsable debe ser personal registrado y activo.');
    }

    public function test_vehiculo_inactivo_igual_al_actual_no_bloquea_la_edicion(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $responsable = $this->persona();
        $horario = (new MaintenanceService)->crearHorario($mantenimiento, [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $vehiculo->update(['status' => false]);

        $this->putJson(route('admin.mantenimientos.horarios.update', [$mantenimiento, $horario]), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])->assertOk();
    }

    public function test_catalogos_excluyen_vehiculo_y_responsable_inactivos(): void
    {
        $this->actingAs(User::factory()->create());
        $mantenimiento = $this->mantenimiento();
        $horario = $this->horario($mantenimiento);
        $vehiculo = $horario->vehiculo;
        $responsable = $horario->responsible;
        $vehiculo->update(['status' => false]);
        $responsable->update(['status' => false]);

        $respuesta = $this->getJson(route('admin.mantenimientos.catalogos'))->assertOk();

        $this->assertNotContains($vehiculo->id, array_column($respuesta->json('vehiculos'), 'id'));
        $this->assertNotContains($responsable->id, array_column($respuesta->json('personal'), 'id'));
    }

    public function test_idempotencia_escape_y_modal_comparten_contrato(): void
    {
        $app = file_get_contents(public_path('js/tads/app-admin.js'));
        $this->assertStringContainsString('if (contenedor._tadsIniciado) return;', $app);
        $this->assertStringContainsString('contenedor._tadsIniciado = true;', $app);

        $gestor = file_get_contents(public_path('js/gestor.js'));
        $this->assertStringContainsString("nodo.textContent = valor ?? '';", $gestor);
        $this->assertStringContainsString("this.tituloEl = this.ventanaEl.querySelector('.modal-title');", $gestor);
        $this->assertStringContainsString("this.cuerpoEl = this.ventanaEl.querySelector('.modal-body');", $gestor);

        $partial = file_get_contents(resource_path('views/admin/partials/ventana-formulario.blade.php'));
        $this->assertStringContainsString('id="tituloVentana"', $partial);
        $this->assertStringContainsString('class="modal-title"', $partial);
        $this->assertStringContainsString('<div class="modal-body"></div>', $partial);
    }

    public function test_boton_nuevo_se_oculta_sin_permiso_de_crear(): void
    {
        $usuario = User::factory()->create(['role' => 'operador']);
        PermisoDeUsuario::create(['user_id' => $usuario->id, 'permission' => 'maintenance.view']);
        $this->actingAs($usuario);
        $mantenimiento = $this->mantenimiento();

        $this->get(route('admin.mantenimientos.horarios.index', $mantenimiento))
            ->assertOk()
            ->assertSee('id="botonNuevo"', false)
            ->assertSee('data-permisos="maintenance.view"', false)
            ->assertDontSee('data-permisos="*"', false);

        $app = file_get_contents(public_path('js/tads/app-admin.js'));
        $this->assertStringContainsString('ocultar(\'#botonNuevo\', `${prefijo}.create`, document);', $app);
    }

    public function test_nombre_con_ampersand_y_comillas_no_se_doble_escapa(): void
    {
        $nombre = 'Mantenimiento & "Jefe" <script>';
        $mantenimiento = (new MaintenanceService)->crearMantenimiento([
            'name' => $nombre,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.mantenimientos.horarios.index', $mantenimiento))
            ->assertOk()
            ->assertSee('Mantenimiento &amp; &quot;Jefe&quot; &lt;script&gt; / Horarios', false)
            ->assertDontSee($nombre.' / Horarios', false);
    }
}
