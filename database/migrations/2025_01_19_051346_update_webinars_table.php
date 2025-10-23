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
    Schema::table('webinars', function (Blueprint $table) {
        $table->string('zoho_meeting_key')->unique()->nullable(); // Unique webinar identifier from Zoho
        $table->string('registration_link')->nullable(); // Registration URL
        $table->string('start_link')->nullable(); // Start URL for presenter
        $table->integer('registration_count')->default(0); // Number of registrations
        $table->string('presenter_email')->nullable(); // Email of the presenter
        $table->string('contact_email')->nullable(); // Contact email for webinar
        $table->string('webinar_type')->nullable(); // Webinar type: Live (1) or On-Demand (2)
        $table->dateTime('end_time')->nullable(); // End time of the webinar
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            //
        });
    }
};
