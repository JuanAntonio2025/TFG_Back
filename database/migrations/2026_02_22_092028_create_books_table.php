<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->increments('book_id');
            $table->string('title', 255);
            $table->string('author', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('front_page', 255)->nullable();
            $table->enum('format', ['PDF', 'EPUB']);
            $table->enum('available', ['available', 'unavailable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
