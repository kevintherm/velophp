<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('base'); // base, auth
            $table->string('name')->unique();
            $table->text('description')->nullable();
            
            // System metadata stored directly on collection
            $table->json('fields')->nullable();
            $table->json('api_rules')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('collections');
    }
};
