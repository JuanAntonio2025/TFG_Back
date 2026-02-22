<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidences', function (Blueprint $table) {
            $table->increments('incidence_id');
            $table->unsignedInteger('user_id');
            $table->string('subject', 255);
            $table->string('type_of_incident', 255);
            $table->dateTime('creation_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->enum('status', ['active', 'inactive']);

            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidences');
    }
};
