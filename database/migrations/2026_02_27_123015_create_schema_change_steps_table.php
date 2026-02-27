<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schema_change_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_change_id')->constrained('schema_changes')->cascadeOnDelete();
            $table->string('step_name'); // e.g. AddColumnStep, BackfillStep
            $table->string('status')->default('PENDING'); // PENDING, DONE, FAILED
            $table->json('error_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_change_steps');
    }
};
