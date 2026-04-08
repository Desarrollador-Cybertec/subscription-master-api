<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\InstallationLimit;
use App\Models\InstallationUsage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstallationController extends Controller
{
    public function index(): JsonResponse
    {
        $installations = Installation::with('limits')->get()->map(fn ($inst) => [
            'id' => $inst->id,
            'product' => $inst->product->value,
            'domain' => $inst->domain,
            'status' => $inst->status->value,
            'plan' => $inst->plan->value,
            'expires_at' => $inst->expires_at?->toIso8601String(),
            'is_expired' => $inst->isExpired(),
            'limits' => $inst->limits->mapWithKeys(fn ($l) => [$l->key => $l->value]),
            'created_at' => $inst->created_at->toIso8601String(),
        ]);

        return response()->json($installations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => 'required|string|in:sintyc,chronology',
            'domain' => 'required|string|max:255',
            'plan' => 'required|string|in:trial,enterprise',
            'expires_at' => 'nullable|date|after:now',
            'limits' => 'required|array|min:1',
            'limits.*.key' => 'required|string|max:100',
            'limits.*.value' => 'required|integer|min:0',
        ]);

        $plainKey = Str::random(48);

        $installation = Installation::create([
            'id' => Str::uuid()->toString(),
            'product' => $data['product'],
            'domain' => $data['domain'],
            'status' => 'active',
            'plan' => $data['plan'],
            'api_key_hash' => hash('sha256', $plainKey),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        foreach ($data['limits'] as $limit) {
            InstallationLimit::create([
                'installation_id' => $installation->id,
                'key' => $limit['key'],
                'value' => $limit['value'],
            ]);
        }

        // Initialize usage counters at 0
        $metricMap = [
            'max_users' => ['metric' => 'users_active', 'periodic' => false],
            'executions_per_month' => ['metric' => 'executions', 'periodic' => true],
        ];

        foreach ($data['limits'] as $limit) {
            $mapping = $metricMap[$limit['key']] ?? null;
            if ($mapping) {
                InstallationUsage::create([
                    'installation_id' => $installation->id,
                    'metric' => $mapping['metric'],
                    'value' => 0,
                    'period' => $mapping['periodic'] ? Carbon::now()->format('Y-m') : null,
                ]);
            }
        }

        $installation->load('limits');

        return response()->json([
            'id' => $installation->id,
            'product' => $installation->product->value,
            'domain' => $installation->domain,
            'status' => $installation->status->value,
            'plan' => $installation->plan->value,
            'expires_at' => $installation->expires_at?->toIso8601String(),
            'limits' => $installation->limits->mapWithKeys(fn ($l) => [$l->key => $l->value]),
            'api_key' => $plainKey,
            'message' => 'Save this API key now. It cannot be retrieved again.',
        ], 201);
    }

    public function show(Installation $installation): JsonResponse
    {
        $installation->load('limits', 'usages');

        return response()->json([
            'id' => $installation->id,
            'product' => $installation->product->value,
            'domain' => $installation->domain,
            'status' => $installation->status->value,
            'plan' => $installation->plan->value,
            'expires_at' => $installation->expires_at?->toIso8601String(),
            'is_expired' => $installation->isExpired(),
            'limits' => $installation->limits->mapWithKeys(fn ($l) => [$l->key => $l->value]),
            'usage' => $installation->usages->mapWithKeys(fn ($u) => [$u->metric => $u->value]),
            'created_at' => $installation->created_at->toIso8601String(),
        ]);
    }

    public function update(Request $request, Installation $installation): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:active,expired,suspended',
            'plan' => 'sometimes|string|in:trial,enterprise',
            'expires_at' => 'nullable|date',
            'limits' => 'sometimes|array',
            'limits.*.key' => 'required_with:limits|string|max:100',
            'limits.*.value' => 'required_with:limits|integer|min:0',
        ]);

        $installation->update(collect($data)->only(['domain', 'status', 'plan', 'expires_at'])->toArray());

        if (isset($data['limits'])) {
            foreach ($data['limits'] as $limit) {
                InstallationLimit::updateOrCreate(
                    [
                        'installation_id' => $installation->id,
                        'key' => $limit['key'],
                    ],
                    ['value' => $limit['value']]
                );
            }
        }

        $installation->load('limits', 'usages');

        return response()->json([
            'id' => $installation->id,
            'product' => $installation->product->value,
            'domain' => $installation->domain,
            'status' => $installation->status->value,
            'plan' => $installation->plan->value,
            'expires_at' => $installation->expires_at?->toIso8601String(),
            'is_expired' => $installation->isExpired(),
            'limits' => $installation->limits->mapWithKeys(fn ($l) => [$l->key => $l->value]),
            'usage' => $installation->usages->mapWithKeys(fn ($u) => [$u->metric => $u->value]),
        ]);
    }

    public function entitlements(Installation $installation): JsonResponse
    {
        $installation->load('limits', 'usages');

        $usages = $installation->usages->mapWithKeys(fn ($u) => [$u->metric => $u->value]);
        $limits = $installation->limits->mapWithKeys(fn ($l) => [$l->key => $l->value]);

        return response()->json([
            'installation_id' => $installation->id,
            'product' => $installation->product->value,
            'status' => $installation->status->value,
            'plan' => $installation->plan->value,
            'expires_at' => $installation->expires_at?->toIso8601String(),
            'is_expired' => $installation->isExpired(),
            'limits' => $limits,
            'usage' => $usages,
        ]);
    }

    public function regenerateApiKey(Installation $installation): JsonResponse
    {
        $plainKey = Str::random(48);

        $installation->update([
            'api_key_hash' => hash('sha256', $plainKey),
        ]);

        return response()->json([
            'installation_id' => $installation->id,
            'product' => $installation->product->value,
            'domain' => $installation->domain,
            'api_key' => $plainKey,
            'message' => 'Save this key now. It cannot be retrieved again.',
        ]);
    }

    public function auditLogs(Request $request, Installation $installation): JsonResponse
    {
        $logs = AuditLog::where('installation_id', $installation->id)
            ->orderByDesc('created_at')
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'result' => $log->result,
                'request_data' => $log->request_data,
                'response_data' => $log->response_data,
                'reference_id' => $log->reference_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json($logs);
    }
}
