<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleField extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'field_id',
        'type',
    ];
}
