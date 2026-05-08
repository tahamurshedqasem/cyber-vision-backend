<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'status',
        'threats',
        'report',
        'confidence'
    ];

    protected $casts = [
        'report' => 'array', // JSON report auto-casted to array
    ];
}
