<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incidence extends Model
{
    protected $table = 'incidences';
    protected $primaryKey = 'incidence_id';
    public $timestamps = false;
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
        'subject',
        'type_of_incident',
        'creation_date',
        'status',
    ];

    protected $casts = [
        'creation_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'incidence_id', 'incidence_id');
    }
}
