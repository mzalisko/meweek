<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 16); // user | system | webhook
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old')->nullable();
            $table->json('new')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('severity', 16); // critical | warning
            $table->string('kind', 32); // failover | slot_exhausted | site_stale | webhook_anomaly
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('message');
            $table->string('status', 16)->default('new'); // new | acknowledged
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['site_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('audit_logs');
    }
};
