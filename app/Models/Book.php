<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $table = 'books';
    protected $primaryKey = 'book_id';
    public $timestamps = false;
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EPUB = 'epub';

    protected $fillable = [
        'title',
        'author',
        'description',
        'price',
        'front_page',
        'file_path',
        'format',
        'available',
        'featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'featured' => 'boolean',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'book_category', 'book_id', 'category_id');
    }

    public function carts()
    {
        return $this->belongsToMany(Cart::class, 'book_cart', 'book_id', 'cart_id')
            ->withPivot('quantity');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'book_order', 'book_id', 'order_id')
            ->withPivot('unit_price');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'book_id', 'book_id');
    }
}
