<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('e164', 20)->unique();
            $table->string('label')->nullable();
            $table->string('status', 16)->default('active'); // active | down
            $table->timestamp('down_since')->nullable();
            $table->timestamps();
        });

        Schema::create('phone_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_value_id')->unique()->constrained('data_values')->cascadeOnDelete();
            $table->string('return_mode', 16)->default('auto'); // auto | sticky
            $table->string('exhaustion_policy', 16)->default('hide'); // hide | show_last | emergency
            $table->string('emergency_number', 20)->nullable();
            // без FK: циклічна залежність із number_entries
            $table->unsignedBigInteger('pinned_number_entry_id')->nullable();
            $table->unsignedBigInteger('current_number_entry_id')->nullable();
            $table->string('last_active_e164', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('number_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('priority');
            $table->timestamps();
            $table->unique(['phone_slot_id', 'priority']);
            $table->unique(['phone_slot_id', 'phone_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_entries');
        Schema::dropIfExists('phone_slots');
        Schema::dropIfExists('phone_numbers');
    }
};
