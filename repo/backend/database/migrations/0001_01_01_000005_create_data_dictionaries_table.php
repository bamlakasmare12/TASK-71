<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_dictionaries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100); // service_type, eligibility, breach_reason, etc.
            $table->string('key', 100);
            $table->string('label', 255);
            $table->jsonb('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'key']);
            $table->index('type');
        });

        Schema::create('form_rules', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 100); // e.g. 'service', 'reservation', 'user'
            $table->string('field', 100);
            $table->jsonb('rules'); // {required: true, min: 3, max: 255, regex: '...'}
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_rules');
        Schema::dropIfExists('data_dictionaries');
    }
};
