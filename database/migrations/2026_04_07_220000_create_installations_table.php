<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->string('domain');
            $table->string('status')->default('active');
            $table->string('plan')->default('trial');
            $table->string('api_key_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installations');
    }
};
