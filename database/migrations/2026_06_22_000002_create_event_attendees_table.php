<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('reminder_3d_sent_at')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};
