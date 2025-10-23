<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['email']); // Drop unique constraint
        });
    }

    public function down()
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unique('email'); // Restore unique constraint if needed
        });
    }
};