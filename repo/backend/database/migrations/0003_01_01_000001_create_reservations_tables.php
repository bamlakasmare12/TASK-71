<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('capacity')->default(1);
            $table->integer('booked_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['service_id', 'start_time']);
            $table->index(['start_time', 'is_active']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('time_slot_id')->constrained()->onDelete('cascade');
            $table->string('status', 30)->default('pending');
            // pending, confirmed, checked_in, completed, cancelled, no_show, partial_attendance
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamp('expires_at')->nullable(); // 30 min expiry for pending
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['time_slot_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
            // Prevent double-booking same slot by same user
            $table->unique(['user_id', 'time_slot_id']);
        });

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type', 50); // late_cancellation, no_show
            $table->decimal('fee_amount', 10, 2)->default(0.00);
            $table->integer('points_deducted')->default(0);
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('time_slots');
    }
};
