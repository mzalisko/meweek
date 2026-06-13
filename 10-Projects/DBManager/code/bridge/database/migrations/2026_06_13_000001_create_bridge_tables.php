<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('token_hash')->unique();
            $table->string('ping_url')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->json('payload');
            $table->timestamps();
            $table->index('token_hash', 'published_sites_token_lookup');
        });

        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['token_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('published_sites');
    }
};
