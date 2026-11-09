<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaner_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaner_id')->constrained('cleaners')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Mon ... 6=Sun
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
            $table->timestamps();

            $table->unique(['cleaner_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaner_working_hours');
    }
};
