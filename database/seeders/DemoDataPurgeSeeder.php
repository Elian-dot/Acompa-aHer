<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Elimina únicamente los datos creados por DemoDataSeeder.
 */
class DemoDataPurgeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $reports = Report::query()->where('description', 'like', DemoDataSeeder::MARKER.'%')->delete();

            $users = User::query()->where('email', 'like', '%@'.DemoDataSeeder::EMAIL_DOMAIN)->get();

            foreach ($users as $user) {
                $personalTeams = $user->teams()->where('is_personal', true)->get();
                $user->delete();
                $personalTeams->each->delete();
            }

            $this->command?->info("Datos demo eliminados: {$reports} reportes y {$users->count()} usuarias.");
        });
    }
}
