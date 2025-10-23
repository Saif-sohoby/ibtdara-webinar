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
    Schema::table('webinars', function (Blueprint $table) {
        $table->dropColumn('zoho_meeting_key');
    });
}

public function down()
{
    Schema::table('webinars', function (Blueprint $table) {
        $table->string('zoho_meeting_key')->nullable();
    });
}

};
