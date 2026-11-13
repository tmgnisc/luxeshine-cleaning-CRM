<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_feed_id')->constrained()->cascadeOnDelete();
            $table->string('uid');
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->unique(['calendar_feed_id', 'uid']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
