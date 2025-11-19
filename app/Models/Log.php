<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'actor',
        'type',
        'action',
        'details',
        'school_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
