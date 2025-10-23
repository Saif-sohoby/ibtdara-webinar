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
    Schema::create('webinars', function (Blueprint $table) {
        $table->id();
        $table->string('topic');
        $table->string('agenda');
        $table->timestamp('start_time');
        $table->integer('duration'); // in seconds
        $table->string('timezone');
        $table->string('zoho_webinar_id')->nullable(); // Store Zoho webinar ID
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinars');
    }
};
