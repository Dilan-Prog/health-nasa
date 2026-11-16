<?php

namespace Tests\Feature;

use App\Livewire\FichaTripulante;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. La misión y la tripulación se crean aquí. */
class FichaTripulanteTest extends TestCase
{
    use DatabaseTransactions;

    private function mision(): Mision
    {
        // id mayor que cualquier misión de desarrollo: es la "actual" de TripulanteActual.
        return Mision::create(['nombre' => 'Test Ficha '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
    }

    private function alta(Mision $m, array $extra = []): Astronauta
    {
        return app(AltaTripulante::class)->registrar(array_merge([
            'nombre' => 'Sofía Rivas Ortega',
            'fecha_nacimiento' => now()->subYears(35)->subDays(10)->toDateString(),
            'sexo' => 'femenino', 'rol' => 'Médico de vuelo', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana',
            'altura_m' => 1.66, 'peso_kg' => 60.4, 'grupo_sanguineo' => 'O-',
            'aptitud_vuelo' => 'apto_restricciones', 'restricciones' => 'Sin EVA hasta la revisión oftalmológica.',
            'linea_base' => ['frecuencia_cardiaca' => 62, 'glucosa' => 88],
            'alergias' => [['sustancia' => 'Penicilina', 'reaccion' => 'Anafilaxia', 'gravedad' => 'grave']],
            'medicamentos' => [['nombre' => 'Melatonina', 'dosis_texto' => '3 mg', 'activo' => true]],
            'contactos' => [['nombre' => 'Andrés Rivas', 'parentesco' => 'hermano', 'telefono' => '+52 55 1234 5678', 'principal' => true]],
        ], $extra), $m);
    }

    private function actual(Mision $m): void
    {
        session(['mision_id' => $m->id]);
    }

    public function test_muestra_cabecera_listas_y_bloques_proximamente(): void
    {
        $m = $this->mision();
        $a = $this->alta($m);
        $this->actual($m);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('Sofía Rivas Ortega')
            ->assertSee('Médico de vuelo')
            ->assertSee('Apto con restricciones')
            ->assertSee('Alergia grave: Penicilina')
            ->assertSee('Grupo O−', false)
            ->assertSee('Restricción de vuelo:')
            ->assertSee('Sin EVA hasta la revisión oftalmológica.')
            ->assertSee('Melatonina 3 mg')
            ->assertSee('La app no receta ni cambia dosis.')
            ->assertSee('Andrés Rivas')
            ->assertSee('Principal')
            ->assertSee('Editar expediente')
            ->assertSee('PRÓXIMAMENTE')
            ->assertSee('Podrás vincular el reloj del tripulante cuando esta función esté disponible')
            ->assertSee('Línea base personal');
    }

    public function test_la_tabla_lista_los_14_marcadores_y_usa_solo_datos_reales(): void
    {
        $m = $this->mision();
        $a = $this->alta($m);
        $this->actual($m);
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'frecuencia_cardiaca', 'valores' => ['valor' => 70], 'medido_en' => now('UTC'), 'origen' => 'manual']);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertViewHas('linea', function ($filas) {
                $porClave = collect($filas)->keyBy('clave');
                $fc = $porClave['frecuencia_cardiaca'];
                $glu = $porClave['glucosa'];

                return count($filas) === 14
                    && $fc['base'] === '62' && $fc['ultimo'] === '70' && $fc['delta'] === '+8' && $fc['estado'] !== 'neu'
                    // Con línea base pero sin ninguna medición: no se inventa valor ni estado.
                    && $glu['base'] === '88' && $glu['ultimo'] === null && $glu['delta'] === null && $glu['estado'] === 'neu'
                    && $porClave['imc']['base'] === null;
            })
            ->assertSee('Sin medir');
    }

    public function test_completitud_coincide_con_el_servicio(): void
    {
        $m = $this->mision();
        $a = $this->alta($m);
        $this->actual($m);
        $pct = app(AltaTripulante::class)->completitud($a)['porcentaje'];

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertViewHas('comp', fn ($c) => $c['porcentaje'] === $pct)
            ->assertSee($pct.' %');
    }

    public function test_listas_vacias_muestran_mensaje(): void
    {
        $m = $this->mision();
        $a = $this->alta($m, ['alergias' => [], 'medicamentos' => [], 'contactos' => [], 'aptitud_vuelo' => 'apto', 'restricciones' => null, 'linea_base' => []]);
        $this->actual($m);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('Sin alergias registradas.')
            ->assertSee('Sin medicamentos registrados.')
            ->assertSee('Sin contactos registrados.')
            ->assertDontSee('Restricción de vuelo:');
    }

    public function test_un_tripulante_de_otra_mision_da_404(): void
    {
        $otra = $this->mision();
        $ajeno = $this->alta($otra, ['nombre' => 'Ajeno Otra Misión']);
        $actual = $this->mision();
        $this->alta($actual, ['nombre' => 'Propio']);
        $this->actual($actual);

        Livewire::test(FichaTripulante::class, ['astronauta' => $ajeno])->assertStatus(404);
    }

    public function test_la_ruta_responde_y_404_para_ajeno(): void
    {
        $otra = $this->mision();
        $ajeno = $this->alta($otra, ['nombre' => 'Ajeno Otra Misión']);
        $actual = $this->mision();
        $propio = $this->alta($actual, ['nombre' => 'Propio Tripulante']);
        $this->actual($actual);

        $this->get(route('tripulacion.ficha', $propio))->assertOk()->assertSee('Propio Tripulante');
        $this->get(route('tripulacion.ficha', $ajeno))->assertNotFound();
    }
}
