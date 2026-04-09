<?php

namespace App\Services;

use App\Enums\InstallationStatus;
use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\InstallationUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    /**
     * Authorize an action for an installation.
     * Fail-closed: unknown actions or missing config → denied.
     */
    public function authorize(Installation $installation, string $action, int $quantity = 1): array
    {
        // Suspended installations are fully blocked
        if ($installation->isSuspended()) {
            return $this->denied('Installation is suspended', $installation->status->value);
        }

        // Resolve action configuration
        $actionConfig = config("subscription.actions.{$installation->product->value}.{$action}");

        if (!$actionConfig) {
            // Fail closed: unknown actions are denied
            return $this->denied('Unknown action', $installation->status->value);
        }

        // Growth actions require active (non-expired) status
        if ($actionConfig['requires_active'] && $installation->isExpired()) {
            return $this->denied(
                'Subscription expired. Growth actions are blocked.',
                $installation->isExpired() ? 'expired' : $installation->status->value
            );
        }

        // Check count-based limits if applicable
        $metric = $actionConfig['metric'] ?? null;
        $limitKey = $actionConfig['limit_key'] ?? null;

        if ($metric && $limitKey) {
            $limit = $installation->limits()->where('key', $limitKey)->first();

            if (!$limit) {
                // Fail closed: no limit configured
                return $this->denied('No limit configured for this action', $installation->status->value);
            }

            $period = ($actionConfig['periodic'] ?? false) ? Carbon::now()->format('Y-m') : null;
            $currentUsage = $this->getCurrentUsage($installation, $metric, $period);
            $remaining = max(0, $limit->value - $currentUsage);

            if ($currentUsage + $quantity > $limit->value) {
                return $this->denied(
                    'Limit exceeded',
                    $installation->status->value,
                    $limit->value,
                    $currentUsage,
                    $remaining
                );
            }

            return $this->allowed(
                $installation->status->value,
                $limit->value,
                $currentUsage,
                max(0, $limit->value - $currentUsage)
            );
        }

        // Status-only check passed
        return $this->allowed($installation->status->value);
    }

    /**
     * Record a usage report. Supports idempotency via reference_id.
     */
    public function reportUsage(Installation $installation, string $metric, int $value, ?string $referenceId = null): array
    {
        $metric = $this->normalizeMetric($metric);
        $period = $this->isPeriodicMetric($metric) ? Carbon::now()->format('Y-m') : null;

        // Idempotency check
        if ($referenceId) {
            $existing = AuditLog::where('installation_id', $installation->id)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing) {
                return $existing->response_data;
            }
        }

        $usage = InstallationUsage::firstOrCreate(
            [
                'installation_id' => $installation->id,
                'metric' => $metric,
                'period' => $period,
            ],
            ['value' => 0]
        );

        // Atomic update — CASE prevents the counter from going below zero on decrements
        $safeValue = (int) $value;
        DB::table('installation_usages')
            ->where('id', $usage->id)
            ->update(['value' => DB::raw('CASE WHEN value + ' . $safeValue . ' > 0 THEN value + ' . $safeValue . ' ELSE 0 END')]);
        $usage->refresh();

        return [
            'recorded' => true,
            'metric' => $metric,
            'current' => $usage->value,
            'period' => $period,
            'reference_id' => $referenceId,
        ];
    }

    /**
     * Get the full entitlements snapshot for an installation.
     */
    public function getEntitlements(Installation $installation): array
    {
        $installation->load('limits');

        $limits = $installation->limits->mapWithKeys(fn ($limit) => [$limit->key => $limit->value]);

        $usages = [];
        foreach ($installation->limits as $limit) {
            $metric = $this->limitKeyToMetric($limit->key);
            $periodic = $this->isPeriodicMetric($metric);
            $period = $periodic ? Carbon::now()->format('Y-m') : null;

            $usages[$metric] = $this->getCurrentUsage($installation, $metric, $period);
        }

        return [
            'installation_id' => $installation->id,
            'product' => $installation->product->value,
            'status' => $installation->status->value,
            'plan' => $installation->plan->value,
            'expires_at' => $installation->expires_at?->toIso8601String(),
            'is_expired' => $installation->isExpired(),
            'limits' => $limits,
            'usage' => $usages,
        ];
    }

    private function getCurrentUsage(Installation $installation, string $metric, ?string $period): int
    {
        return (int) InstallationUsage::where('installation_id', $installation->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('value');
    }

    private function normalizeMetric(string $metric): string
    {
        return config("subscription.metric_aliases.{$metric}", $metric);
    }

    private function isPeriodicMetric(string $metric): bool
    {
        return in_array($metric, ['executions']);
    }

    private function limitKeyToMetric(string $limitKey): string
    {
        return match ($limitKey) {
            'max_users' => 'users_active',
            'executions_per_month' => 'executions',
            default => $limitKey,
        };
    }

    private function allowed(string $status, ?int $limit = null, ?int $current = null, ?int $remaining = null): array
    {
        return [
            'allowed' => true,
            'reason' => null,
            'limit' => $limit,
            'current' => $current,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }

    private function denied(string $reason, string $status, ?int $limit = null, ?int $current = null, ?int $remaining = null): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'limit' => $limit,
            'current' => $current,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }
}
