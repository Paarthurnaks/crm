<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleDiskEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'disk_entity_id',
    ];
}
