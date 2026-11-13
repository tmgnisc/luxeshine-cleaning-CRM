<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source', 50)->default('airbnb');
            $table->text('url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->unsignedInteger('events_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
    }
};
