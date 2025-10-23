<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('participant_webinar', function (Blueprint $table) {
        $table->id();
        $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_webinar');
    }
};
