<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action', 100); // login, login_failed, logout, password_change, lockout, role_change, etc.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('severity', 20)->default('info'); // info, warning, critical
            $table->timestamp('created_at');

            $table->index(['user_id', 'action']);
            $table->index(['action', 'created_at']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
