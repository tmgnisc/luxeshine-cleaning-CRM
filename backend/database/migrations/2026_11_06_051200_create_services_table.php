<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('short_description', 255);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price_per_hour', 10, 2);
            $table->decimal('default_duration_hours', 4, 1);
            $table->decimal('min_duration_hours', 4, 1)->default(1);
            $table->decimal('max_duration_hours', 4, 1)->default(8);
            $table->boolean('is_live')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
