<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip');
            $table->unsignedInteger('port')->default(4370);
            $table->boolean('is_active')->default(true);
            $table->string('serial_number')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['ip', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
