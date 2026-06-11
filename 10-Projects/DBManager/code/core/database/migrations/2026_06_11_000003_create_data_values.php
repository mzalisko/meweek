<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('value_type_id')->constrained();
            $table->string('key');
            $table->string('scope_type', 8); // group | site
            $table->unsignedBigInteger('scope_id');
            $table->json('content')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['key', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('data_value_geo_tag', function (Blueprint $table) {
            $table->foreignId('data_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geo_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['data_value_id', 'geo_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_value_geo_tag');
        Schema::dropIfExists('data_values');
    }
};
