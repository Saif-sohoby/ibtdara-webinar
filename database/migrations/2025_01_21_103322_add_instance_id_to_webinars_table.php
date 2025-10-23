<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->string('instance_id')->nullable()->after('zoho_webinar_id'); // Add instanceId column
        });
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn('instance_id');
        });
    }
};
