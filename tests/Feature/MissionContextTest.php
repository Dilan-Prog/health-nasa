<?php

namespace Tests\Feature;

use App\Livewire\ContextoMision;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. */
class MissionContextTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shows_the_mission_context(): void
    {
        $this->seed(ExpedicionSeeder::class);
        session(['mision_id' => Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->id]);

        Livewire::test(ContextoMision::class)
            ->assertSee(ExpedicionSeeder::MISION)
            ->assertSee('Space station (LEO)')
            ->assertSee('TIMELINE', false)
            ->assertSee('UPCOMING EVENTS', false)
            ->assertSee('5 aboard');
    }
}
