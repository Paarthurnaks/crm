<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleUserField extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'field_id',
        'type',
    ];
}
