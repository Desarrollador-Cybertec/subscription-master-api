<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizeTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key-sintyc';
    private Installation $installation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installation = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'sintyc',
            'domain' => 'api.test.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', $this->apiKey),
            'expires_at' => now()->addYear(),
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

    public function test_authorize_allows_when_within_limit(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'allowed' => true,
                'limit' => 10,
                'current' => 8,
                'remaining' => 2,
                'status' => 'active',
            ]);
    }

    public function test_authorize_denies_when_limit_exceeded(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
            'quantity' => 3, // 8 + 3 = 11 > 10
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertStatus(403)
            ->assertJson([
                'allowed' => false,
                'reason' => 'Limit exceeded',
            ]);
    }

    public function test_authorize_denies_when_expired(): void
    {
        $this->installation->update(['expires_at' => now()->subDay()]);

        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertStatus(403)
            ->assertJson([
                'allowed' => false,
                'reason' => 'Subscription expired. Growth actions are blocked.',
            ]);
    }

    public function test_authorize_denies_when_suspended(): void
    {
        $this->installation->update(['status' => 'suspended']);

        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertStatus(403)
            ->assertJson([
                'allowed' => false,
                'reason' => 'Installation is suspended',
            ]);
    }

    public function test_authorize_denies_unknown_action(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'unknown_action',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertStatus(403)
            ->assertJson([
                'allowed' => false,
                'reason' => 'Unknown action',
            ]);
    }

    public function test_authorize_rejects_without_api_key(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'API key required']);
    }

    public function test_authorize_rejects_invalid_api_key(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
        ], ['X-API-Key' => 'wrong-key']);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid API key']);
    }

    public function test_authorize_rejects_installation_id_mismatch(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'installation_id' => Str::uuid()->toString(),
            'action' => 'create_user',
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Installation ID mismatch']);
    }

    public function test_authorize_creates_audit_log(): void
    {
        $this->postJson('/api/internal/authorize', [
            'action' => 'create_user',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $this->assertDatabaseHas('audit_logs', [
            'installation_id' => $this->installation->id,
            'action' => 'create_user',
            'result' => 'allowed',
        ]);
    }

    public function test_authorize_status_only_action(): void
    {
        $response = $this->postJson('/api/internal/authorize', [
            'action' => 'create_area',
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'allowed' => true,
                'status' => 'active',
            ]);
    }
}
