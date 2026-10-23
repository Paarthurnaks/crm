<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'client_id',
    ];
}
