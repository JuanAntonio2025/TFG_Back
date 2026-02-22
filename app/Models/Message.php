<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'incidence_id',
        'user_id',
        'message',
        'sent_date',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
    ];

    public function incidence()
    {
        return $this->belongsTo(Incidence::class, 'incidence_id', 'incidence_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
