<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->default('base');
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->json('api_rules')->nullable();

            $table->timestamp('schema_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
