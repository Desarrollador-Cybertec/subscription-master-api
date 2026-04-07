<?php

namespace App\Models;

use App\Enums\InstallationStatus;
use App\Enums\PlanType;
use App\Enums\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'product',
        'domain',
        'status',
        'plan',
        'api_key_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'product' => Product::class,
            'status' => InstallationStatus::class,
            'plan' => PlanType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function limits(): HasMany
    {
        return $this->hasMany(InstallationLimit::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(InstallationUsage::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isExpired(): bool
    {
        return $this->status === InstallationStatus::Expired
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isSuspended(): bool
    {
        return $this->status === InstallationStatus::Suspended;
    }

    public function isActive(): bool
    {
        return $this->status === InstallationStatus::Active && !$this->isExpired();
    }
}
