<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('collection_id');
            $table->string('operation');
            $table->string('table_name');
            $table->timestamp('started_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_jobs');
    }
};
