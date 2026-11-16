<?php

namespace Tests\Feature;

use App\Livewire\Botiquin;
use App\Models\BotiquinItem;
use Database\Seeders\BotiquinSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the database data. */
class MedicalKitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BotiquinSeeder::class);
    }

    public function test_seeder_is_idempotent_and_does_not_overwrite_edits(): void
    {
        $total = array_sum(array_map('count', BotiquinItem::GRUPOS));
        $this->assertSame($total, BotiquinItem::count());

        BotiquinItem::where('nombre', 'melatonina')->update(['stock' => 77]);
        $this->seed(BotiquinSeeder::class);

        $this->assertSame($total, BotiquinItem::count());
        $this->assertSame(77, BotiquinItem::where('nombre', 'melatonina')->value('stock'));
        $this->assertSame(0, BotiquinItem::where('contraindicaciones', '!=', BotiquinItem::PENDIENTE)->count());
    }

    public function test_lists_the_five_groups_the_notice_and_the_example_label(): void
    {
        $t = Livewire::test(Botiquin::class)
            ->assertSee('Informational inventory. This is not a prescription; consult the Flight Surgeon')
            ->assertSee('example value')
            ->assertSee('Pending medical verification');
        // Display labels in English; the stored Spanish group keys stay as they are.
        $labels = [
            'Mareo por adaptación' => 'Space adaptation (motion sickness)',
            'Sueño y fatiga' => 'Sleep & fatigue',
            'Analgesia e inflamación' => 'Pain & inflammation',
            'Antimicrobianos y antivirales' => 'Antimicrobials & antivirals',
            'Emergencias' => 'Emergency',
        ];
        $this->assertSame(array_keys(BotiquinItem::GRUPOS), array_keys($labels));
        foreach ($labels as $label) {
            $t->assertSee($label);
        }
        $this->assertCount(5, BotiquinItem::GRUPOS);
    }

    public function test_inventory_contains_no_dose_units(): void
    {
        $html = Livewire::test(Botiquin::class)->html();
        $inv = substr($html, (int) strpos($html, 'bq-inventario'));
        $this->assertDoesNotMatchRegularExpression('/\b(mg|mcg|µg|ml\/kg|ml|UI|IU)\b/u', strip_tags($inv));
        foreach (BotiquinItem::all() as $i) {
            $this->assertDoesNotMatchRegularExpression('/\b(mg|mcg|ml\/kg|ml|UI|IU)\b/u', $i->nombre.' '.$i->unidad.' '.$i->nota);
        }
    }

    public function test_editing_stock_and_expiry_persists_and_removes_example_flag(): void
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

    public function test_validates_stock_and_expiry(): void
    {
        $item = BotiquinItem::first();
        $before = $item->stock;

        Livewire::test(Botiquin::class)
            ->call('editar', $item->id)
            ->set('stock', '-1')->call('guardar')->assertHasErrors(['stock'])
            ->set('stock', 'abc')->call('guardar')->assertHasErrors(['stock'])
            ->set('stock', '5')->set('caducidad', 'no-es-fecha')->call('guardar')->assertHasErrors(['caducidad']);

        $this->assertSame($before, $item->fresh()->stock);
    }

    public function test_expiry_status(): void
    {
        $today = now();
        $make = fn ($d) => (new BotiquinItem)->forceFill(['caducidad' => $d]);

        $this->assertSame('vencido', $make($today->copy()->subDay())->estadoCaducidad($today)[0]);
        $this->assertSame('pronto', $make($today->copy()->addDays(30))->estadoCaducidad($today)[0]);
        $this->assertSame('vigente', $make($today->copy()->addDays(365))->estadoCaducidad($today)[0]);
        $this->assertSame('sin', $make(null)->estadoCaducidad($today)[0]);
    }

    public function test_route_responds(): void
    {
        $this->get('/botiquin')->assertOk()->assertSee('Informational medical kit', false);
    }
}
