<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = 'carts';
    protected $primaryKey = 'cart_id';
    public $timestamps = false;
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'creation_date',
        'expiration_date',
        'active',
    ];

    protected $casts = [
        'creation_date' => 'datetime',
        'expiration_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_cart', 'cart_id', 'book_id')
            ->withPivot('quantity');
    }

    public function getCalculatedTotalAttribute(): float
    {
        return (float) $this->books->sum(function ($book) {
            return ((float) $book->price) * ((int) $book->pivot->quantity);
        });
    }
}
