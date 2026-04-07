<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key-entitlement';
    private Installation $installation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installation = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'sintyc',
            'domain' => 'api.ent-test.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', $this->apiKey),
            'expires_at' => now()->addMonths(6),
        ]);

        InstallationLimit::create([
            'installation_id' => $this->installation->id,
            'key' => 'max_users',
            'value' => 10,
        ]);

        InstallationUsage::create([
            'installation_id' => $this->installation->id,
            'metric' => 'users_active',
            'value' => 8,
            'period' => null,
        ]);
    }

    public function test_entitlements_returns_full_snapshot(): void
    {
        $response = $this->getJson('/api/internal/entitlements', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJson([
                'installation_id' => $this->installation->id,
                'product' => 'sintyc',
                'status' => 'active',
                'plan' => 'enterprise',
                'is_expired' => false,
                'limits' => ['max_users' => 10],
                'usage' => ['users_active' => 8],
            ]);
    }

    public function test_entitlements_shows_expired_status(): void
    {
        $this->installation->update(['expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/internal/entitlements', [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertOk()
            ->assertJson([
                'is_expired' => true,
            ]);
    }

    public function test_entitlements_rejects_without_auth(): void
    {
        $response = $this->getJson('/api/internal/entitlements');

        $response->assertStatus(401);
    }
}
