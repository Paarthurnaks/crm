<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'client_id',
    ];
}
