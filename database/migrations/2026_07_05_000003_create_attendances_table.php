<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('uid');
            $table->string('userid');
            $table->unsignedTinyInteger('state')->default(0);
            $table->unsignedTinyInteger('type')->default(0);
            $table->dateTime('punched_at');
            $table->timestamps();

            $table->unique(['device_id', 'uid', 'punched_at', 'type']);
            $table->index('punched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
