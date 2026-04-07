<?php

namespace Database\Seeders;

use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstallationSeeder extends Seeder
{
    /**
     * Seed demo installations for testing.
     *
     * API Keys (raw, for testing only):
     *   S!NTyC:      sintyc-test-key-2026
     *   Chronology:  chronology-test-key-2026
     */
    public function run(): void
    {
        // S!NTyC Installation
        $sintyc = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'sintyc',
            'domain' => 'api.sintyc-demo.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', 'sintyc-test-key-2026'),
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
            'value' => 8,
            'period' => null,
        ]);

        // Chronology Installation
        $chronology = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'chronology',
            'domain' => 'api.chronology-demo.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', 'chronology-test-key-2026'),
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
            'value' => 3200,
            'period' => now()->format('Y-m'),
        ]);
    }
}
