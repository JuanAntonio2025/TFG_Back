<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('message_id');
            $table->unsignedInteger('incidence_id');
            $table->unsignedInteger('user_id');
            $table->string('message', 255);
            $table->dateTime('sent_date')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('incidence_id')->references('incidence_id')->on('incidences');
            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
