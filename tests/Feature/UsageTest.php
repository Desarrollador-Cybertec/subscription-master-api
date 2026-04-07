<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key-chronology';
    private Installation $installation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installation = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => 'chronology',
            'domain' => 'api.chrono-test.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'api_key_hash' => hash('sha256', $this->apiKey),
            'expires_at' => now()->addYear(),
        ]);

        InstallationLimit::create([
            'installation_id' => $this->installation->id,
            'key' => 'executions_per_month',
            'value' => 5000,
        ]);
    }

    public function test_usage_report_increments_counter(): void
    {
        $response = $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'recorded' => true,
                'metric' => 'executions',
                'current' => 1,
            ]);

        // Second call increments
        $response = $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 5,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'recorded' => true,
                'current' => 6,
            ]);
    }

    public function test_usage_report_idempotent_with_reference_id(): void
    {
        $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 10,
            'reference_id' => 'import_batch_123',
        ], ['X-API-Key' => $this->apiKey]);

        // Second call with same reference_id should not double-count
        $response = $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 10,
            'reference_id' => 'import_batch_123',
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'recorded' => true,
                'current' => 10, // not 20
            ]);
    }

    public function test_usage_report_normalizes_metric(): void
    {
        $response = $this->postJson('/api/internal/usage', [
            'metric' => 'execution', // singular
            'value' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $response->assertOk()
            ->assertJson([
                'metric' => 'executions', // normalized to plural
            ]);
    }

    public function test_usage_report_creates_audit_log(): void
    {
        $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 1,
            'reference_id' => 'batch_456',
        ], ['X-API-Key' => $this->apiKey]);

        $this->assertDatabaseHas('audit_logs', [
            'installation_id' => $this->installation->id,
            'action' => 'usage:execution',
            'result' => 'recorded',
            'reference_id' => 'batch_456',
        ]);
    }

    public function test_authorize_then_usage_flow(): void
    {
        // Step 1: Authorize
        $authResponse = $this->postJson('/api/internal/authorize', [
            'action' => 'run_execution',
            'quantity' => 1,
        ], ['X-API-Key' => $this->apiKey]);

        $authResponse->assertOk()
            ->assertJson(['allowed' => true]);

        // Step 2: Report usage
        $usageResponse = $this->postJson('/api/internal/usage', [
            'metric' => 'execution',
            'value' => 1,
            'reference_id' => 'job_001',
        ], ['X-API-Key' => $this->apiKey]);

        $usageResponse->assertOk()
            ->assertJson(['recorded' => true]);

        // Step 3: Verify entitlements updated
        $entResponse = $this->getJson('/api/internal/entitlements', [
            'X-API-Key' => $this->apiKey,
        ]);

        $entResponse->assertOk()
            ->assertJson([
                'product' => 'chronology',
                'usage' => ['executions' => 1],
            ]);
    }
}
