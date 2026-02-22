<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BANNED = 'banned';

    protected $fillable = [
        'name',
        'email',
        'password',
        'register_date',
        'last_access',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'register_date' => 'datetime',
        'last_access' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'user_id', 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id', 'user_id');
    }

    public function incidences()
    {
        return $this->hasMany(Incidence::class, 'user_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'user_id', 'user_id');
    }

    public function activeCart()
    {
        return $this->hasOne(Cart::class, 'user_id', 'user_id')
            ->where('active', 'active');
    }

    public function libraryBooks()
    {
        return $this->belongsToMany(Book::class, 'book_order', 'order_id', 'book_id')
            ->join('orders', 'orders.order_id', '=', 'book_order.order_id')
            ->whereColumn('orders.user_id', 'users.user_id');
    }
}
