<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BorrowRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'book_id',
        'duration_days',
        'status',
        'requested_at',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejected_by',
        'rejection_reason',
        'librarian_note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'duration_days' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function loan()
    {
        return $this->hasOne(Loan::class, 'request_id');
    }
}
