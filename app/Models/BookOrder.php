<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BookOrder extends Pivot
{
    protected $table = 'book_order';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'book_id',
        'unit_price',
    ];
}
