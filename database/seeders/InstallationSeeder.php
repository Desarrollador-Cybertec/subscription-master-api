<?php

namespace Database\Seeders;

use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstallationSeeder extends Seeder
{
    public function run(): void
    {
        $sintycKey = Str::random(48);
        $chronologyKey = Str::random(48);

        // ─── S!NTyC ─────────────────────────────────────────
        $sintyc = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'sintyc',
            'domain' => 'service.cyberteconline.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', $sintycKey),
            'expires_at' => now()->addYear(),
        ]);

        InstallationLimit::create([
            'installation_id' => $sintyc->id,
            'key' => 'max_users',
            'value' => 10,
        ]);

        InstallationUsage::create([
            'installation_id' => $sintyc->id,
            'metric' => 'users_active',
            'value' => 0,
            'period' => null,
        ]);

        // ─── Chronology ─────────────────────────────────────
        $chronology = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'chronology',
            'domain' => 'api.cyberteconline.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', $chronologyKey),
            'expires_at' => now()->addYear(),
        ]);

        InstallationLimit::create([
            'installation_id' => $chronology->id,
            'key' => 'executions_per_month',
            'value' => 5000,
        ]);

        InstallationUsage::create([
            'installation_id' => $chronology->id,
            'metric' => 'executions',
            'value' => 0,
            'period' => now()->format('Y-m'),
        ]);

        // ─── Output ─────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════════════╗');
        $this->command->info('║          INSTALLATIONS CREATED — SAVE THESE KEYS!           ║');
        $this->command->info('╠══════════════════════════════════════════════════════════════╣');
        $this->command->newLine();
        $this->command->warn("  S!NTyC  (service.cyberteconline.com)");
        $this->command->line("  Installation ID : {$sintyc->id}");
        $this->command->line("  API Key         : {$sintycKey}");
        $this->command->newLine();
        $this->command->warn("  Chronology  (api.cyberteconline.com)");
        $this->command->line("  Installation ID : {$chronology->id}");
        $this->command->line("  API Key         : {$chronologyKey}");
        $this->command->newLine();
        $this->command->info('╚══════════════════════════════════════════════════════════════╝');
        $this->command->newLine();
    }
}
