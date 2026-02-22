<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BookCategory extends Pivot
{
    protected $table = 'book_category';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'book_id',
        'category_id',
    ];
}
