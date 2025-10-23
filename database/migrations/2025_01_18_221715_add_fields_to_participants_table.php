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
    Schema::table('participants', function (Blueprint $table) {
        $table->string('mobile')->nullable(); // Mobile phone number
        $table->string('job_title')->nullable(); // Job title
        $table->text('why_business_in_ksa')->nullable(); // Reason for doing business in KSA
    });
}

public function down(): void
{
    Schema::table('participants', function (Blueprint $table) {
        $table->dropColumn(['mobile', 'job_title', 'why_business_in_ksa']);
    });
}

};
