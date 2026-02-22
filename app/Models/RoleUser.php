<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleUser extends Pivot
{
    protected $table = 'role_user';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'role_id',
    ];
}
