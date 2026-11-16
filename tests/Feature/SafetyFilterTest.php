<?php

namespace Tests\Feature;

use App\Models\BotiquinItem;
use App\Services\Ia\FiltroSeguridad;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class FiltroSeguridadTest extends TestCase
{
    use DatabaseTransactions;

    private function filtro(): FiltroSeguridad
    {
        return new FiltroSeguridad();
    }

    #[DataProvider('dosis')]
    public function test_rechaza_dosis_y_unidades_de_dosis(string $texto): void
    {
        $this->assertSame(FiltroSeguridad::DOSIS, $this->filtro()->revisar($texto));
    }

    public static function dosis(): array
    {
        return [
            'mg' => ['Repite la medición y toma 500 mg si persiste.'],
            'mcg' => ['Una pauta de 20 mcg al día.'],
            'ml' => ['Bebe 250 ml cada hora.'],
            'comprimidos' => ['Dos comprimidos cada 8 horas: 2 comprimidos.'],
            'un comprimido' => ['1 comprimido por la noche.'],
            'palabra dosis' => ['Ajusta la dosis según el peso.'],
        ];
    }

    public function test_rechaza_nombres_de_medicamentos_del_botiquin_como_recomendacion(): void
    {
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filtro()->revisar('Considera usar ibuprofeno para el malestar.'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filtro()->revisar('IBUPROFENO ayudaría.'), 'mayúsculas');
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filtro()->revisar('Una opción es la melatonina.'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filtro()->revisar('Podría servir el ONDANSETRON.'), 'sin tilde');
    }

    public function test_rechaza_medicamentos_que_estan_en_la_tabla_del_botiquin(): void
    {
        BotiquinItem::create(['nombre' => 'Farmaco de prueba XQ', 'grupo' => 'Emergencias', 'stock' => 1, 'unidad' => 'u']);

        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filtro()->revisar('Usa farmaco de prueba XQ ahora.'));
    }

    #[DataProvider('diagnosticos')]
    public function test_rechaza_lenguaje_de_diagnostico_definitivo(string $texto): void
    {
        $this->assertSame(FiltroSeguridad::DIAGNOSTICO, $this->filtro()->revisar($texto));
    }

    public static function diagnosticos(): array
    {
        return [
            'tienes' => ['Tienes hipoxia.'],
            'padeces' => ['Padeces una infección.'],
            'diagnostico:' => ['Diagnóstico: deshidratación.'],
            'diagnosticado' => ['Has sido diagnosticado con arritmia.'],
            'estas enfermo' => ['Estás enfermo.'],
            'tratamiento con' => ['Inicia un tratamiento con antibióticos.'],
        ];
    }

    public function test_permite_lenguaje_de_hipotesis_y_unidades_de_medida(): void
    {
        $f = $this->filtro();
        $this->assertNull($f->revisar('Posible hipoxia: la saturación está en precaución. Confirma con el médico de vuelo.'));
        $this->assertNull($f->revisar('Glucosa 98 mg/dL, dentro de la base personal.'), 'mg/dL es una unidad de medida');
        $this->assertNull($f->revisar('Dosis acumulada de radiación dentro del límite de misión.'));
        $this->assertTrue($f->permitido('Repite la medición en unos minutos y registra cómo te sientes.'));
    }

    public function test_permite_la_medicacion_registrada_como_contexto_pero_no_como_recomendacion(): void
    {
        $f = $this->filtro();
        $this->assertNull($f->revisar('Medicación registrada: Ibuprofeno (motivo: dolor de espalda)'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $f->revisar('Se sugiere ibuprofeno.'));
        $this->assertSame(FiltroSeguridad::DOSIS, $f->revisar('Medicación registrada: Ibuprofeno 400 mg'), 'el contexto tampoco lleva dosis');
    }

    public function test_revisar_todos_devuelve_la_primera_causa(): void
    {
        $this->assertNull($this->filtro()->revisarTodos(['Posible fiebre.', 'Repite la medición.']));
        $this->assertSame(FiltroSeguridad::DIAGNOSTICO, $this->filtro()->revisarTodos(['Posible fiebre.', 'Tienes fiebre.']));
    }
}
