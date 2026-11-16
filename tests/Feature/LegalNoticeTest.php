<?php

namespace Tests\Feature;

use App\Livewire\AvisoLegal;
use Livewire\Livewire;
use Tests\TestCase;

class AvisoLegalTest extends TestCase
{
    public function test_muestra_aviso_y_fuentes(): void
    {
        Livewire::test(AvisoLegal::class)
            ->assertSee('prototipo educativo')
            ->assertSee('no sustituye a un profesional médico')
            ->assertSee('NASA-STD-3001 Vol. 1 Rev. C')
            ->assertSee('NASA HRP')
            ->assertSee('CSA')
            ->assertSee('pendientes de revisión médica');
    }

    public function test_la_ruta_responde(): void
    {
        $this->get('/aviso-legal')->assertOk()->assertSee('Fuentes citadas');
    }
}
