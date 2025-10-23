<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('webinars', function (Blueprint $table) {
        $table->dropColumn(['timezone', 'presenter_email', 'contact_email']);
    });
}

public function down()
{
    Schema::table('webinars', function (Blueprint $table) {
        $table->string('timezone')->nullable();
        $table->string('presenter_email')->nullable();
        $table->string('contact_email')->nullable();
    });
}

};
