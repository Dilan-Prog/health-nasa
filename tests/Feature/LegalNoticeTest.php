<?php

namespace Tests\Feature;

use App\Livewire\AvisoLegal;
use Livewire\Livewire;
use Tests\TestCase;

class LegalNoticeTest extends TestCase
{
    public function test_shows_notice_and_sources(): void
    {
        Livewire::test(AvisoLegal::class)
            ->assertSee('educational prototype')
            ->assertSee('does not replace a medical professional')
            ->assertSee('NASA-STD-3001 Vol. 1 Rev. C')
            ->assertSee('NASA HRP')
            ->assertSee('CSA')
            ->assertSee('pending medical review');
    }

    public function test_route_responds(): void
    {
        $this->get('/aviso-legal')->assertOk()->assertSee('Sources cited');
    }
}
