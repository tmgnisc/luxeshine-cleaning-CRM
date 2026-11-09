<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('cleaner_id')->nullable()->constrained('cleaners')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->cascadeOnUpdate()->nullOnDelete();
            $table->json('address_snapshot')->nullable();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->decimal('duration_hours', 4, 1);
            $table->time('end_time');
            $table->decimal('price_per_hour', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->boolean('price_overridden')->default(false);
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending')->index();
            $table->boolean('is_paid')->default(false)->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('source', ['website', 'admin'])->default('website');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('scheduled_date');
            $table->index(['cleaner_id', 'scheduled_date']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
