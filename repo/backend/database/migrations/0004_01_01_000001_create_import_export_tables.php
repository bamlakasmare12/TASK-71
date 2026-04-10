<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pg_trgm for fuzzy duplicate detection
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Add GIN trigram index on services.title for fast similarity search
        DB::statement('CREATE INDEX IF NOT EXISTS idx_services_title_trgm ON services USING gin (title gin_trgm_ops)');

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('entity', 50); // services, users
            $table->string('filename', 255);
            $table->string('format', 10); // csv, json
            $table->string('status', 30)->default('pending');
            // pending, mapping, processing, completed, failed
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->integer('duplicate_count')->default(0);
            $table->jsonb('field_mapping')->nullable(); // {"source_col": "dest_col"}
            $table->string('conflict_strategy', 30)->default('prefer_newest');
            // prefer_newest, admin_override, skip
            $table->jsonb('error_log')->nullable(); // [{row: 3, field: "title", error: "..."}]
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('entity');
        });

        Schema::create('import_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->onDelete('cascade');
            $table->string('entity', 50);
            $table->unsignedBigInteger('existing_id')->nullable();
            $table->jsonb('incoming_data');
            $table->jsonb('existing_data')->nullable();
            $table->decimal('similarity_score', 5, 4)->default(0);
            $table->string('match_type', 30); // exact_id, title_similarity
            $table->string('resolution', 30)->nullable(); // overwrite, skip, merged
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['import_batch_id', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_conflicts');
        Schema::dropIfExists('import_batches');
        DB::statement('DROP INDEX IF EXISTS idx_services_title_trgm');
    }
};
