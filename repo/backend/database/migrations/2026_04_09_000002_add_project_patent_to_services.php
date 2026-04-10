<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('project_number', 100)->nullable()->after('category');
            $table->string('patent_number', 100)->nullable()->after('project_number');
            $table->unique('project_number');
            $table->unique('patent_number');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['project_number']);
            $table->dropUnique(['patent_number']);
            $table->dropColumn(['project_number', 'patent_number']);
        });
    }
};
