<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\InstallationUsage;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('usage:sync
    {installation : Installation UUID}
    {metric : Metric name (e.g. users_active)}
    {value : Absolute value to set (integer >= 0)}')]
#[Description('Manually set a usage counter to its correct absolute value (fixes drifted counters).')]
class SyncInstallationUsage extends Command
{
    public function handle(): int
    {
        $installationId = $this->argument('installation');
        $metric         = $this->argument('metric');
        $value          = (int) $this->argument('value');

        if ($value < 0) {
            $this->error('Value must be >= 0.');
            return self::FAILURE;
        }

        $installation = Installation::find($installationId);

        if (!$installation) {
            $this->error("Installation not found: {$installationId}");
            return self::FAILURE;
        }

        // Resolve aliases (e.g. user_active → users_active)
        $aliases = config('subscription.metric_aliases', []);
        $metric  = $aliases[$metric] ?? $metric;

        $periodicMetrics = ['executions'];
        $period = in_array($metric, $periodicMetrics) ? Carbon::now()->format('Y-m') : null;

        $usage = InstallationUsage::firstOrCreate(
            [
                'installation_id' => $installation->id,
                'metric'          => $metric,
                'period'          => $period,
            ],
            ['value' => 0]
        );

        $previous = $usage->value;
        $usage->update(['value' => $value]);

        AuditLog::create([
            'installation_id' => $installation->id,
            'action'          => "admin:sync_usage:{$metric}",
            'result'          => 'corrected',
            'request_data'    => ['metric' => $metric, 'value' => $value],
            'response_data'   => ['previous' => $previous, 'current' => $value, 'period' => $period],
            'reference_id'    => null,
            'ip_address'      => '127.0.0.1',
        ]);

        $this->info("  Installation : {$installation->id} ({$installation->domain})");
        $this->info("  Metric       : {$metric}" . ($period ? " (period: {$period})" : ''));
        $this->info("  Previous     : {$previous}");
        $this->info("  New value    : {$value}");
        $this->newLine();
        $this->info('Usage counter corrected successfully.');

        return self::SUCCESS;
    }
}
