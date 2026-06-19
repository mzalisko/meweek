<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_databases', function (Blueprint $table) {
            $table->id();
            $table->string('sha256', 64)->unique();
            $table->longText('bytes'); // base64 байтів бази
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_databases');
    }
};
