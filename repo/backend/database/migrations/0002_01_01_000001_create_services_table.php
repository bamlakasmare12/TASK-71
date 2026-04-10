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
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description');
            $table->string('service_type', 100); // references data_dictionaries
            $table->text('eligibility_notes')->nullable();
            $table->jsonb('target_audience')->default('[]'); // ["faculty","staff","graduate"]
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('is_free')->default(true);
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('service_type');
            $table->index('category');
            $table->index('is_active');
            $table->index('price');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('service_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');

            $table->unique(['service_id', 'tag_id']);
        });

        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at');

            $table->unique(['user_id', 'service_id']);
        });

        Schema::create('user_recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->timestamp('viewed_at');

            $table->index(['user_id', 'viewed_at']);
            $table->unique(['user_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recently_viewed');
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('service_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('services');
    }
};
