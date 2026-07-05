<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('uid');
            $table->string('userid');
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('role')->default(0);
            $table->string('cardno')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
