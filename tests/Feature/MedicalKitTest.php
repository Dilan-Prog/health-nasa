<?php

namespace Tests\Feature;

use App\Livewire\Botiquin;
use App\Models\BotiquinItem;
use Database\Seeders\BotiquinSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD. */
class BotiquinTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BotiquinSeeder::class);
    }

    public function test_el_seeder_es_idempotente_y_no_pisa_ediciones(): void
    {
        $total = array_sum(array_map('count', BotiquinItem::GRUPOS));
        $this->assertSame($total, BotiquinItem::count());

        BotiquinItem::where('nombre', 'melatonina')->update(['stock' => 77]);
        $this->seed(BotiquinSeeder::class);

        $this->assertSame($total, BotiquinItem::count());
        $this->assertSame(77, BotiquinItem::where('nombre', 'melatonina')->value('stock'));
        $this->assertSame(0, BotiquinItem::where('contraindicaciones', '!=', BotiquinItem::PENDIENTE)->count());
    }

    public function test_lista_los_5_grupos_el_aviso_y_la_etiqueta_de_ejemplo(): void
    {
        $t = Livewire::test(Botiquin::class)
            ->assertSee('Inventario informativo. No es una prescripción; consulta al médico de vuelo')
            ->assertSee('valor de ejemplo')
            ->assertSee('Pendiente de verificación médica');
        foreach (array_keys(BotiquinItem::GRUPOS) as $grupo) {
            $t->assertSee($grupo);
        }
        $this->assertCount(5, BotiquinItem::GRUPOS);
    }

    public function test_el_inventario_no_contiene_unidades_de_dosis(): void
    {
        $html = Livewire::test(Botiquin::class)->html();
        $inv = substr($html, (int) strpos($html, 'bq-inventario'));
        $this->assertDoesNotMatchRegularExpression('/\b(mg|mcg|µg|ml\/kg|ml|UI|IU)\b/u', strip_tags($inv));
        foreach (BotiquinItem::all() as $i) {
            $this->assertDoesNotMatchRegularExpression('/\b(mg|mcg|ml\/kg|ml|UI|IU)\b/u', $i->nombre.' '.$i->unidad.' '.$i->nota);
        }
    }

    public function test_editar_stock_y_caducidad_persiste_y_quita_la_marca_de_ejemplo(): void
    {
        $item = BotiquinItem::where('nombre', 'ibuprofeno')->firstOrFail();

        Livewire::test(Botiquin::class)
            ->call('editar', $item->id)
            ->set('stock', '42')
            ->set('caducidad', '2030-05-20')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('editando', null);

        $item->refresh();
        $this->assertSame(42, $item->stock);
        $this->assertSame('2030-05-20', $item->caducidad->format('Y-m-d'));
        $this->assertFalse($item->valor_ejemplo);
    }

    public function test_valida_stock_y_caducidad(): void
    {
        $item = BotiquinItem::first();
        $antes = $item->stock;

        Livewire::test(Botiquin::class)
            ->call('editar', $item->id)
            ->set('stock', '-1')->call('guardar')->assertHasErrors(['stock'])
            ->set('stock', 'abc')->call('guardar')->assertHasErrors(['stock'])
            ->set('stock', '5')->set('caducidad', 'no-es-fecha')->call('guardar')->assertHasErrors(['caducidad']);

        $this->assertSame($antes, $item->fresh()->stock);
    }

    public function test_estado_de_caducidad(): void
    {
        $hoy = now();
        $mk = fn ($d) => (new BotiquinItem)->forceFill(['caducidad' => $d]);

        $this->assertSame('vencido', $mk($hoy->copy()->subDay())->estadoCaducidad($hoy)[0]);
        $this->assertSame('pronto', $mk($hoy->copy()->addDays(30))->estadoCaducidad($hoy)[0]);
        $this->assertSame('vigente', $mk($hoy->copy()->addDays(365))->estadoCaducidad($hoy)[0]);
        $this->assertSame('sin', $mk(null)->estadoCaducidad($hoy)[0]);
    }

    public function test_la_ruta_responde(): void
    {
        $this->get('/botiquin')->assertOk()->assertSee('Botiquín informativo', false);
    }
}
