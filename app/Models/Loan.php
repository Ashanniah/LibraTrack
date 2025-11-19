<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'student_id',
        'book_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'extended_due_at',
        'status',
        'school_id',
    ];

    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_at' => 'date',
        'returned_at' => 'datetime',
        'extended_due_at' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function borrowRequest()
    {
        return $this->belongsTo(BorrowRequest::class, 'request_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function getLoanStatusAttribute()
    {
        if ($this->status === 'returned') {
            return 'returned';
        }
        
        $dueDate = $this->extended_due_at ?? $this->due_at;
        $today = now()->toDateString();
        
        return $dueDate < $today ? 'overdue' : 'on-time';
    }
}
