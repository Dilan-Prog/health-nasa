<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AstronautasSeeder::class);
        $this->call(ExpedicionSeeder::class);
        $this->call(HistorialExpedicionSeeder::class);
        $this->call(RutinasSeeder::class);
        $this->call(RequisitosSeeder::class);
        if (class_exists(BotiquinSeeder::class)) {
            $this->call(BotiquinSeeder::class);
        }

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
