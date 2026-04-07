<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id');
            $table->string('action');
            $table->string('result');
            $table->json('request_data');
            $table->json('response_data');
            $table->string('reference_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('installation_id')
                ->references('id')
                ->on('installations')
                ->onDelete('cascade');

            $table->index(['installation_id', 'created_at']);
            $table->index(['installation_id', 'reference_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
