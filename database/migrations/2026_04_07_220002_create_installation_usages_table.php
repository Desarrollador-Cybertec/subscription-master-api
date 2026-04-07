<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_usages', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id');
            $table->string('metric');
            $table->unsignedInteger('value')->default(0);
            $table->string('period')->nullable();
            $table->timestamps();

            $table->foreign('installation_id')
                ->references('id')
                ->on('installations')
                ->onDelete('cascade');

            $table->unique(['installation_id', 'metric', 'period'], 'usage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_usages');
    }
};
