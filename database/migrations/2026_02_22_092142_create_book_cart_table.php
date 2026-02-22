<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_cart', function (Blueprint $table) {
            $table->unsignedInteger('cart_id');
            $table->unsignedInteger('book_id');
            $table->integer('quantity');

            $table->primary(['cart_id', 'book_id']);

            $table->foreign('cart_id')
                ->references('cart_id')
                ->on('carts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('book_id')
                ->references('book_id')
                ->on('books')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_cart');
    }
};
