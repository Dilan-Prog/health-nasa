<?php

namespace Tests\Feature;

use App\Models\BotiquinItem;
use App\Services\Ia\FiltroSeguridad;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DatabaseTransactions (not RefreshDatabase): never wipes the local database data.
 *
 * The sample sentences passed to the filter are intentionally Spanish fixtures: the deterministic filter matches
 * Spanish dose, drug and diagnosis wording, so its inputs must stay in that language.
 */
class SafetyFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function filter(): FiltroSeguridad
    {
        return new FiltroSeguridad();
    }

    #[DataProvider('doses')]
    public function test_rejects_doses_and_dose_units(string $text): void
    {
        $this->assertSame(FiltroSeguridad::DOSIS, $this->filter()->revisar($text));
    }

    public static function doses(): array
    {
        return [
            'mg' => ['Repite la medición y toma 500 mg si persiste.'],
            'mcg' => ['Una pauta de 20 mcg al día.'],
            'ml' => ['Bebe 250 ml cada hora.'],
            'tablets' => ['Dos comprimidos cada 8 horas: 2 comprimidos.'],
            'one tablet' => ['1 comprimido por la noche.'],
            'the word dose' => ['Ajusta la dosis según el peso.'],
        ];
    }

    public function test_rejects_kit_drug_names_as_a_recommendation(): void
    {
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filter()->revisar('Considera usar ibuprofeno para el malestar.'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filter()->revisar('IBUPROFENO ayudaría.'), 'uppercase');
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filter()->revisar('Una opción es la melatonina.'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filter()->revisar('Podría servir el ONDANSETRON.'), 'without accent');
    }

    public function test_rejects_drugs_that_are_in_the_medical_kit_table(): void
    {
        BotiquinItem::create(['nombre' => 'Farmaco de prueba XQ', 'grupo' => 'Emergencias', 'stock' => 1, 'unidad' => 'u']);

        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->filter()->revisar('Usa farmaco de prueba XQ ahora.'));
    }

    #[DataProvider('diagnoses')]
    public function test_rejects_definitive_diagnosis_language(string $text): void
    {
        $this->assertSame(FiltroSeguridad::DIAGNOSTICO, $this->filter()->revisar($text));
    }

    public static function diagnoses(): array
    {
        return [
            'you have' => ['Tienes hipoxia.'],
            'you suffer from' => ['Padeces una infección.'],
            'diagnosis colon' => ['Diagnóstico: deshidratación.'],
            'diagnosed' => ['Has sido diagnosticado con arritmia.'],
            'you are sick' => ['Estás enfermo.'],
            'treatment with' => ['Inicia un tratamiento con antibióticos.'],
        ];
    }

    public function test_allows_hypothesis_language_and_measurement_units(): void
    {
        $f = $this->filter();
        $this->assertNull($f->revisar('Posible hipoxia: la saturación está en precaución. Confirma con el médico de vuelo.'));
        $this->assertNull($f->revisar('Glucosa 98 mg/dL, dentro de la base personal.'), 'mg/dL is a measurement unit');
        $this->assertNull($f->revisar('Dosis acumulada de radiación dentro del límite de misión.'));
        $this->assertTrue($f->permitido('Repite la medición en unos minutos y registra cómo te sientes.'));
    }

    public function test_allows_logged_medication_as_context_but_not_as_a_recommendation(): void
    {
        $f = $this->filter();
        $this->assertNull($f->revisar('Medicación registrada: Ibuprofeno (motivo: dolor de espalda)'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $f->revisar('Se sugiere ibuprofeno.'));
        $this->assertSame(FiltroSeguridad::DOSIS, $f->revisar('Medicación registrada: Ibuprofeno 400 mg'), 'the context must not carry doses either');
    }

    public function test_review_all_returns_the_first_cause(): void
    {
        $this->assertNull($this->filter()->revisarTodos(['Posible fiebre.', 'Repite la medición.']));
        $this->assertSame(FiltroSeguridad::DIAGNOSTICO, $this->filter()->revisarTodos(['Posible fiebre.', 'Tienes fiebre.']));
    }
}
