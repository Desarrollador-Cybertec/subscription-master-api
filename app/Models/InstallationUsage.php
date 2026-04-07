<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationUsage extends Model
{
    protected $fillable = [
        'installation_id',
        'metric',
        'value',
        'period',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
