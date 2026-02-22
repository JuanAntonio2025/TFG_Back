<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BookCart extends Pivot
{
    protected $table = 'book_cart';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'cart_id',
        'book_id',
        'quantity',
    ];
}
