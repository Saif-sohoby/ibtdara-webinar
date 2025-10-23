<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('email')->nullable()->change(); // Make email optional
        });
    }

    public function down()
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change(); // Revert if needed
        });
    }
};
