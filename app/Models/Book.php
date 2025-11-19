<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'isbn',
        'author',
        'category',
        'quantity',
        'publisher',
        'date_published',
        'cover',
        'rating',
        'archived',
    ];

    protected $casts = [
        'date_published' => 'date',
        'rating' => 'decimal:2',
        'archived' => 'boolean',
        'quantity' => 'integer',
    ];

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function borrowRequests()
    {
        return $this->hasMany(BorrowRequest::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function getCoverUrlAttribute()
    {
        return $this->cover ? "/libratrack/uploads/covers/{$this->cover}" : null;
    }
}
